<?php
session_start();
header("Content-type: text/html; charset=utf-8");

$_SESSION['CPM'] = 1;
if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        case "step1":
            $abspath = str_replace('\\', '/', $_POST['abspath']);
            $_SESSION['abspath'] = $abspath;
            if (substr($abspath, strlen($abspath)-1) == "/") {
                $abspath = substr($abspath, 0, strlen($abspath)-1);
            }
            $okWritable = $okVersion = true;
            $okExtensions = true;
            $txt = "";
            $x=1;
            $tab = array(
                $abspath."/install/",
                $abspath."/includes/",
                $abspath."/files/",
                $abspath."/upload/"
            );
            foreach ($tab as $elem) {
                if (is_writable($elem) == true) {
                    $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">'.$elem.'&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
                } else {
                    $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">'.$elem.'&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
                    $okWritable = false;
                }
                $x++;
            }

            if (!extension_loaded('mysql')) {
                $okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mysql\"&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mysql\"&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('mcrypt')) {
                $okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mcrypt\"&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mcrypt\"&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('openssl')) {
                $okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"openssl\"&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"openssl\"&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('gmp')) {
                $okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"gmp\"&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"gmp\"&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('bcmath')) {
                $okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"openssl\"&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"openssl\"&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (!extension_loaded('iconv')) {
                $okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"gmp\"&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"gmp\"&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (ini_get('max_execution_time')<60) {
                $okExtensions = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP \"Maximum '.
                    'execution time\" is set to '.ini_get('max_execution_time').' seconds.'.
                    ' Please try to set to 60s at least during installation.&nbsp;'.
                    '&nbsp;<img src=\"images/minus-circle.png\"></span> <br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP \"Maximum '.
                    'execution time\" is set to '.ini_get('max_execution_time').' seconds'.
                    '&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }
            if (version_compare(phpversion(), '5.3.0', '<')) {
                $okVersion = false;
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP version '.phpversion().' is not OK (minimum is 5.3.0) &nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
            } else {
                $txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP version '.phpversion().' is OK&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
            }

            if ($okWritable == true && $okExtensions == true && $okVersion == true) {
                echo 'document.getElementById("but_next").disabled = "";';
                echo 'document.getElementById("status_step1").innerHTML = "Elements are OK.";';
                echo 'gauge.modify($("pbar"),{values:[0.20,1]});';
            } else {
                echo 'document.getElementById("but_next").disabled = "disabled";';
                echo 'document.getElementById("status_step1").innerHTML = "Correct the shown errors and click on button Launch to refresh";';
                echo 'gauge.modify($("pbar"),{values:[0.10,1]});';
            }

            echo 'document.getElementById("res_step1").innerHTML = "'.$txt.'";';
            echo 'document.getElementById("loader").style.display = "none";';
            break;

        /**
         * STEP 2
         */
        case "step2":
            //decrypt the password
            require_once '../includes/libraries/Encryption/Crypt/aesctr.php';  // AES Counter Mode implementation
            $dbPassword = Encryption\Crypt\aesctr::decrypt($_POST['db_password'], "cpm", 128);

            $res = "";
            // connexion
            if (@mysql_connect($_POST['db_host'], $_POST['db_login'], $dbPassword)) {
                if (@mysql_select_db($_POST['db_bdd'])) {
                    echo 'gauge.modify($("pbar"),{values:[0.40,1]});';
                    $res = "Connection is successfull";
                    echo 'document.getElementById("but_next").disabled = "";';
                } else {
                    echo 'gauge.modify($("pbar"),{values:[0.30,1]});';
                    $res = "Impossible to get connected to table";
                    echo 'document.getElementById("but_next").disabled = "disabled";';
                }
            } else {
                echo 'gauge.modify($("pbar"),{values:[0.30,1]});';
                $res = "Impossible to get connected to server";
                echo 'document.getElementById("but_next").disabled = "disabled";';
            }
            echo 'document.getElementById("res_step2").innerHTML = "'.$res.'";';
            echo 'document.getElementById("loader").style.display = "none";';
            break;

        /**
         * STEP 3
         */
        case "step3":
            if (is_dir($_POST['skPath'])) {
                if (is_writable(dirname($_POST['skPath']))) {
                    echo 'document.getElementById("sk_path_res").innerHTML = "<img src=\"images/tick.png\">";
                    gauge.modify($("pbar"),{values:[0.60,1]});
                    document.getElementById("but_next").disabled = "";
                    document.getElementById("loader").style.display = "none";';
                } else {
                    echo 'document.getElementById("sk_path_res").innerHTML = "<img src=\"images/exclamation-red.png\"> The Directory must be writable!";
                    document.getElementById("loader").style.display = "none";
                    document.getElementById("but_next").disabled = "disabled";';
                }
            } else {
                echo 'document.getElementById("sk_path_res").innerHTML = "<img src=\"images/exclamation-red.png\"> This is not a Directory!";
                document.getElementById("loader").style.display = "none";
                document.getElementById("but_next").disabled = "disabled";';
            }
            break;

        /**
         * STEP 4
         */
        case "step4":
            // Populate dataBase
            $res = "";

            @mysql_connect($_SESSION['db_host'], $_SESSION['db_login'], $_SESSION['db_pw']);
            @mysql_select_db($_SESSION['db_bdd']);
            $dbTmp = mysql_connect($_SESSION['db_host'], $_SESSION['db_login'], $_SESSION['db_pw']);
            mysql_select_db($_SESSION['db_bdd'], $dbTmp);

            //FORCE UTF8 DATABASE
            mysql_query("ALTER DATABASE `".$_SESSION['db_bdd']."` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");

            ## TABLE 2
            $res2 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."items` (
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
                  PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res2) {
                echo 'document.getElementById("tbl_2").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table ITEMS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_2").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 3
            $res3 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."log_items` (
                  `id_item` int(8) NOT NULL,
                  `date` varchar(50) NOT NULL,
                  `id_user` int(8) NOT NULL,
                  `action` varchar(250) NOT NULL,
                  `raison` text NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res3) {
                echo 'document.getElementById("tbl_3").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table LOG_ITEMS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_3").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 4 - MISC
            require_once '../includes/language/english.php';
            require_once '../includes/include.php';
            $res4 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."misc` (
                  `type` varchar(50) NOT NULL,
                  `intitule` varchar(100) NOT NULL,
                  `valeur` varchar(100) NOT NULL
               ) CHARSET=utf8;"
            );
            mysql_query(
                "INSERT INTO `".$_SESSION['tbl_prefix']."misc` (`type`, `intitule`, `valeur`) VALUES
                ('admin', 'max_latest_items', '10'),
                ('admin', 'enable_favourites', '1'),
                ('admin', 'show_last_items', '1'),
                ('admin', 'enable_pf_feature', '0'),
                ('admin', 'log_connections', '0'),
                ('admin', 'log_accessed', '1'),
                ('admin', 'time_format', 'H:i:s'),
                ('admin', 'date_format', 'd/m/Y'),
                ('admin', 'duplicate_folder', '0'),
                ('admin', 'duplicate_item', '0'),
                ('admin', 'number_of_used_pw', '3'),
                ('admin', 'manager_edit', '1'),
                ('admin', 'cpassman_dir', '".$_SESSION['abspath']."'),
                ('admin', 'cpassman_url', '".$_SESSION['url_path']."'),
                ('admin', 'favicon', '".$_SESSION['url_path']."/favico.ico'),
                ('admin', 'path_to_upload_folder', '".$_SESSION['abspath']."/upload'),
                ('admin', 'url_to_upload_folder', '".$_SESSION['url_path']."/upload'),
                ('admin', 'path_to_files_folder', '".$_SESSION['abspath']."/files'),
                ('admin', 'url_to_files_folder', '".$_SESSION['url_path']."/files'),
                ('admin', 'activate_expiration', '0'),
                ('admin','pw_life_duration','0'),
                ('admin','maintenance_mode','1'),
                ('admin','enable_sts','0'),
                ('admin','cpassman_version','".$k['version']."'),
                ('admin','ldap_mode','0'),
                ('admin','richtext','0'),
                ('admin','allow_print','0'),
                ('admin','show_description','1'),
                ('admin','anyone_can_modify','0'),
                ('admin','anyone_can_modify_bydefault','0'),
                ('admin','nb_bad_authentication','0'),
                ('admin','utf8_enabled','1'),
                ('admin','restricted_to','0'),
                ('admin','restricted_to_roles','0'),
                ('admin','enable_send_email_on_user_login','0'),
                ('admin','enable_user_can_create_folders','0'),
                ('admin','insert_manual_entry_item_history','0'),
                ('admin','enable_kb','0'),
                ('admin','enable_email_notification_on_item_shown','0'),
                ('admin','custom_logo',''),
                ('admin','custom_login_text',''),
                ('admin','default_language','english'),
                ('admin', 'send_stats', '".$_SESSION['send_stats']."'),
                ('admin', 'send_mail_on_user_login', '0'),
                ('cron', 'sending_emails', '0'),
                ('admin', 'nb_items_by_query', 'auto'),
                ('admin', 'enable_delete_after_consultation', '0'),
                ('admin', 'enable_personal_saltkey_cookie', '0'),
                ('admin', 'personal_saltkey_cookie_duration', '31'),
                ('admin', 'email_smtp_server', '".$_SESSION['smtp_server']."'),
                ('admin', 'email_smtp_auth', '".$_SESSION['smtp_auth']."'),
                ('admin', 'email_auth_username', '".$_SESSION['smtp_auth_username']."'),
                ('admin', 'email_auth_pwd', '".$_SESSION['smtp_auth_password']."'),
                ('admin', 'email_port', '".$_SESSION['smtp_port']."'),
                ('admin', 'email_from', '".$_SESSION['email_from']."'),
                ('admin', 'email_from_name', '".$_SESSION['email_from_name']."'),
                ('admin', 'pwd_maximum_length', '40'),
                ('admin', '2factors_authentication', '0'),
                ('admin', 'delay_item_edition', '0'),
                ('admin','allow_import','0'),
                ('admin','proxy_ip',''),
                ('admin','proxy_port',''),
                ('admin','upload_maxfilesize','10mb'),
                ('admin','upload_docext','doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx'),
                ('admin','upload_imagesext','jpg,jpeg,gif,png'),
                ('admin','upload_pkgext','7z,rar,tar,zip'),
                ('admin','upload_otherext','sql,xml'),
                ('admin','upload_imageresize_options','1'),
                ('admin','upload_imageresize_width','800'),
                ('admin','upload_imageresize_height','600'),
                ('admin','upload_imageresize_quality','90')
                ;"
            );

            if ($res4) {
                echo 'document.getElementById("tbl_4").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table MISC! '.mysql_error().'";';
                echo 'document.getElementById("tbl_4").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 5 - NEESTED_TREE
            $res5 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."nested_tree` (
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
            if ($res5) {
                echo 'document.getElementById("tbl_5").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table NESTED_TREE! '.mysql_error().'";';
                echo 'document.getElementById("tbl_5").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 6 - RIGHTS
            $res6 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."rights` (
                  `id` int(12) NOT null AUTO_INCREMENT,
                  `tree_id` int(12) NOT NULL,
                  `fonction_id` int(12) NOT NULL,
                  `authorized` tinyint(1) NOT null DEFAULT '0',
                  PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res6) {
                echo 'document.getElementById("tbl_6").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table RIGHTS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_6").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 7 - USERS
            $res7 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."users` (
                  `id` int(12) NOT null AUTO_INCREMENT,
                  `login` varchar(50) NOT NULL,
                  `pw` varchar(200) NOT NULL,
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
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `login` (`login`)
               ) CHARSET=utf8;"
            );
            if ($res7) {
                echo 'document.getElementById("tbl_7").innerHTML = "<img src=\"images/tick.png\">";';
                require_once '../sources/main.functions.php';
                //vérifier que l'admin n'existe pas
                $tmp = mysql_fetch_row(mysql_query("SELECT COUNT(*) FROM `".$_SESSION['tbl_prefix']."users` WHERE login = 'admin'"));
                if ($tmp[0] == 0) {
                    $res8 = mysql_query(
                        "INSERT INTO `".$_SESSION['tbl_prefix']."users` (`id`, `login`, `pw`, `groupes_visibles`, `derniers`, `key_tempo`, `last_pw_change`, `last_pw`, `admin`, `fonction_id`, `groupes_interdits`, `last_connexion`, `gestionnaire`, `email`, `favourites`, `latest_items`, `personal_folder`) VALUES (NULL, 'admin', '".bCrypt('admin','13' )."', '', '', '', '', '', '1', '', '', '', '0', '', '', '', '0')"
                    );
                    if ($res8) {
                        echo 'document.getElementById("tbl_8").innerHTML = "<img src=\"images/tick.png\">";';
                    } else {
                        echo 'document.getElementById("res_step4").innerHTML = "Could not import admin account!";';
                        echo 'document.getElementById("tbl_8").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                        echo 'document.getElementById("loader").style.display = "none";';
                        mysql_close($dbTmp);
                        break;
                    }
                } else {
                    mysql_query("UPDATE `".$_SESSION['tbl_prefix']."users` SET `pw` = '".bCrypt('admin','13' )."' WHERE login = 'admin' AND id = '1'");
                    echo 'document.getElementById("tbl_8").innerHTML = "<img src=\"images/tick.png\">";';
                }
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table USERS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_7").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 8 - TAGS
            $res8 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."tags` (
                  `id` int(12) NOT null AUTO_INCREMENT,
                  `tag` varchar(30) NOT NULL,
                  `item_id` int(12) NOT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `id` (`id`)
               ) CHARSET=utf8;"
            );
            if ($res8) {
                echo 'document.getElementById("tbl_9").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table TAGS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_9").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 9 - LOG_SYSTEM
            $res8 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."log_system` (
                  `id` int(12) NOT null AUTO_INCREMENT,
                  `type` varchar(20) NOT NULL,
                  `date` varchar(30) NOT NULL,
                  `label` text NOT NULL,
                  `qui` varchar(30) NOT NULL,
                  `field_1` varchar(250) NOT NULL,
                  PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res8) {
                echo 'document.getElementById("tbl_10").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table LOG_SYSTEM! '.mysql_error().'";';
                echo 'document.getElementById("tbl_10").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 10 - FILES
            $res9 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."files` (
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
            if ($res9) {
                echo 'document.getElementById("tbl_11").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table FILES! '.mysql_error().'";';
                echo 'document.getElementById("tbl_11").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE CACHE
            $res9 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."cache` (
                `id` int(12) NOT NULL,
                `label` varchar(50) NOT NULL,
                `description` text NOT NULL,
                `tags` text NOT NULL,
                `id_tree` int(12) NOT NULL,
                `perso` tinyint(1) NOT NULL,
                `restricted_to` varchar(200) NOT NULL,
                `login` varchar(200) NOT NULL,
                `folder` varchar(300) NOT NULL,
                `author` varchar(50) NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res9) {
                echo 'document.getElementById("tbl_12").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table FILES! '.mysql_error().'";';
                echo 'document.getElementById("tbl_12").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 13 - ROLES_TITLES
            $res13 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."roles_title` (
                  `id` int(12) NOT null AUTO_INCREMENT,
                  `title` varchar(50) NOT NULL,
                  `allow_pw_change` TINYINT(1) NOT null DEFAULT '0',
                  `complexity` INT(5) NOT null DEFAULT '0',
                  `creator_id` int(11) NOT null DEFAULT '0',
                  PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res13) {
                echo 'document.getElementById("tbl_13").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table ITEMS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_13").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE 14 - ROLES_VALUES
            $res14 = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."roles_values` (
                  `role_id` int(12) NOT NULL,
                  `folder_id` int(12) NOT NULL
                );"
            );
            if ($res14) {
                echo 'document.getElementById("tbl_14").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table ROLES! '.mysql_error().'";';
                echo 'document.getElementById("tbl_14").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE KB
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb` (
                    `id` int(12) NOT null AUTO_INCREMENT,
                    `category_id` int(12) NOT NULL,
                    `label` varchar(200) NOT NULL,
                    `description` text NOT NULL,
                    `author_id` int(12) NOT NULL,
                    `anyone_can_modify` tinyint(1) NOT null DEFAULT '0',
                    PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_15").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table KB! '.mysql_error().'";';
                echo 'document.getElementById("tbl_15").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE KB_CATEGORIES
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb_categories` (
                    `id` int(12) NOT null AUTO_INCREMENT,
                    `category` varchar(50) NOT NULL,
                    PRIMARY KEY (`id`)
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_16").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table KB_CATEGORIES! '.mysql_error().'";';
                echo 'document.getElementById("tbl_16").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }
            /*
            ## TABLE 14 - ROLES_VALUES
            $res14 = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."roles_values` (
                  `role_id` int(12) NOT NULL,
                  `folder_id` int(12) NOT NULL
               ) CHARSET=utf8;");
            if ($res14) {
                echo 'document.getElementById("tbl_14").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table ITEMS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_14").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }
            */
            ## TABLE KB_ITEMS
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb_items` (
                `kb_id` tinyint(12) NOT NULL,
                `item_id` tinyint(12) NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_17").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table KB_ITEMS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_17").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE restriction_to_roles
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."restriction_to_roles` (
                `role_id` int(12) NOT NULL,
                `item_id` int(12) NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_18").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table restriction_to_roles! '.mysql_error().'";';
                echo 'document.getElementById("tbl_18").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE KEYS
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."keys` (
                `table` varchar(25) NOT NULL,
                `id` int(20) NOT NULL,
                `rand_key` varchar(25) NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_19").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table KEYS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_19").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE LANGUAGE
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."languages` (
                `id` INT(10) NOT null AUTO_INCREMENT PRIMARY KEY ,
                `name` VARCHAR(50) NOT null ,
                `label` VARCHAR(50) NOT null ,
                `code` VARCHAR(10) NOT null ,
                `flag` VARCHAR(30) NOT NULL
               ) CHARSET=utf8;"
            );
            mysql_query(
                "INSERT INTO `".$_SESSION['tbl_prefix']."languages` (`id`, `name`, `label`, `code`, `flag`) VALUES
                ('', 'french', 'French' , 'fr', 'fr.png'),
                ('', 'english', 'English' , 'us', 'us.png'),
                ('', 'spanish', 'Spanish' , 'es', 'es.png'),
                ('', 'german', 'German' , 'de', 'de.png'),
                ('', 'czech', 'Czech' , 'cz', 'cz.png'),
                ('', 'italian', 'Italian' , 'it', 'it.png'),
                ('', 'russian', 'Russian' , 'ru', 'ru.png'),
                ('', 'turkish', 'Turkish' , 'tr', 'tr.png'),
                ('', 'norwegian', 'Norwegian' , 'no', 'no.png'),
                ('', 'japanese', 'Japanese' , 'ja', 'ja.png'),
                ('', 'portuguese', 'Portuguese' , 'pr', 'pr.png'),
                ('', 'chinese', 'Chinese' , 'cn', 'cn.png'),
                ('', 'swedish', 'Swedish' , 'se', 'se.png'),
                ('', 'dutch', 'Dutch' , 'nl', 'nl.png');"
            );
            if ($res) {
                echo 'document.getElementById("tbl_20").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table LANGUAGES! '.mysql_error().'";';
                echo 'document.getElementById("tbl_20").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE EMAILS
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."emails` (
                `timestamp` INT(30) NOT null ,
                `subject` VARCHAR(255) NOT null ,
                `body` TEXT NOT null ,
                `receivers` VARCHAR(255) NOT null ,
                `status` VARCHAR(30) NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_21").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table EMAILS! '.mysql_error().'";';
                echo 'document.getElementById("tbl_21").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE AUTOMATIC DELETION
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."automatic_del` (
                `item_id` int(11) NOT NULL,
                `del_enabled` tinyint(1) NOT NULL,
                `del_type` tinyint(1) NOT NULL,
                `del_value` varchar(35) NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_22").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table AUTOMATIC_DEL! '.mysql_error().'";';
                echo 'document.getElementById("tbl_22").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            ## TABLE items_edition
            $res = mysql_query(
                "CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."items_edition` (
                `item_id` int(11) NOT NULL,
                `user_id` int(11) NOT NULL,
                `timestamp` varchar(50) NOT NULL
               ) CHARSET=utf8;"
            );
            if ($res) {
                echo 'document.getElementById("tbl_23").innerHTML = "<img src=\"images/tick.png\">";';
            } else {
                echo 'document.getElementById("res_step4").innerHTML = "An error appears on table items_edition! '.mysql_error().'";';
                echo 'document.getElementById("tbl_23").innerHTML = "<img src=\"images/exclamation-red.png\">";';
                echo 'document.getElementById("loader").style.display = "none";';
                mysql_close($dbTmp);
                break;
            }

            echo 'gauge.modify($("pbar"),{values:[0.80,1]});';
            echo 'document.getElementById("but_next").disabled = "";';
            echo 'document.getElementById("res_step4").innerHTML = "dataBase has been populated";';
            echo 'document.getElementById("loader").style.display = "none";';
            mysql_close($dbTmp);
            break;

        /**
         * STEP 5
         */
        case "step5":
            if (empty($_SESSION['sk_path'])) {
                $skFile = $_SESSION['abspath'].'/includes/sk.php';
                $securePath = $_SESSION['abspath'];
            } else {
                $skFile = $_SESSION['sk_path'].'/sk.php';
                $securePath = $_SESSION['sk_path'];
            }

            $filename = "../includes/settings.php";
            $events = "";
            if (file_exists($filename)) {
                if (!copy($filename, $filename.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                    echo 'document.getElementById("res_step4").innerHTML = "Setting.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.";';
                    echo 'document.getElementById("loader").style.display = "none";';
                    break;
                } else {
                    $events .= "The file $filename already exist. A copy has been created.<br />";
                    unlink($filename);
                }
            }
            $fh = fopen($filename, 'w');

            $result1 = fwrite(
                $fh,
                utf8_encode(
"<?php
global \$lang, \$txt, \$k, \$pathTeampas, \$urlTeampass, \$pwComplexity, \$mngPages;
global \$server, \$user, \$pass, \$database, \$pre, \$db;

### DATABASE connexion parameters ###
\$server = \"".$_SESSION['db_host']."\";
\$user = \"".$_SESSION['db_login']."\";
\$pass = \"".str_replace("$", "\\$", $_SESSION['db_pw'])."\";
\$database = \"".$_SESSION['db_bdd']."\";
\$pre = \"".$_SESSION['tbl_prefix']."\";

@date_default_timezone_set(\$_SESSION['settings']['timezone']);
@define('SECUREPATH', '".$securePath."');
require_once \"".str_replace('\\', '/', $skFile)."\";
?>"
                )
            );
            fclose($fh);
            if ($result1 === false) {
                echo 'document.getElementById("res_step5").innerHTML = "Setting.php file could not be created. Please check the path and the rights.";';
            } else {
                echo 'document.getElementById("step5_settingFile").innerHTML = "<img src=\"images/tick.png\">";';
            }

            //Create sk.php file
            if (file_exists($skFile)) {
                if (!copy($skFile, $skFile.'.'.date("Y_m_d", mktime(0, 0, 0, date('m'), date('d'), date('y'))))) {
                    echo 'document.getElementById("res_step5").innerHTML = "'.$skFile.' file already exists and cannot be renamed. Please do it by yourself and click on button Launch.";';
                    echo 'document.getElementById("loader").style.display = "none";';
                    break;
                } else {
                    $events .= "The file $skFile already exist. A copy has been created.<br />";
                    unlink($skFile);
                }
            }
            $fh = fopen($skFile, 'w');

            $result2 = fwrite(
                $fh,
                utf8_encode(
"<?php
@define('SALT', '".$_SESSION['encrypt_key']."'); //Never Change it once it has been used !!!!!
@define('COST', '13'); // Don't change this.
?>")
            );
            fclose($fh);
            if ($result2 === false) {
                echo 'document.getElementById("res_step5").innerHTML = "Setting.php file has been created.<br />$skFile could not be created. Please check the path and the rights.";';
            } else {
                echo 'document.getElementById("step5_skFile").innerHTML = "<img src=\"images/tick.png\">";';
            }

            //Generate Keys file
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
            $result3 = file_put_contents(substr($skFile, 0, strlen($skFile)-6).$numberOfPairs . "_". $keyLength . "_keys.inc.php", implode("\n", $file));
            if (isset($result3) && $result3 === false) {
                echo 'document.getElementById("res_step5").innerHTML = "Encryption Keys file could not be created. Please check the path and the rights.";';
            } else {
                echo 'document.getElementById("step5_keysFile").innerHTML = "<img src=\"images/tick.png\">";';
            }

            if (isset($result2) && $result2 != false && $result1 != false && $result3 != false) {
                echo 'gauge.modify($("pbar"),{values:[1,1]});';
                echo 'document.getElementById("but_next").disabled = "";';
                echo 'document.getElementById("res_step5").innerHTML = "Operations are successfully completed.";';
                echo 'document.getElementById("loader").style.display = "none";';
            }
            break;
    }
}
