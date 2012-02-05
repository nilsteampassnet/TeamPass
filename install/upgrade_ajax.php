<?php
session_start();

require_once("../includes/language/english.php");
require_once("../includes/include.php");
if(!file_exists("../includes/settings.php")){
	echo 'document.getElementById("res_step1_error").innerHTML = "";';
	echo 'document.getElementById("res_step1_error").innerHTML = "File settings.php does not exist in folder includes/! If it is an upgrade, it should be there, otherwize select install!";';
	echo 'document.getElementById("loader").style.display = "none";';
	exit;
}
require_once("../includes/settings.php");

$_SESSION['CPM'] = 1;

################
## Function permits to get the value from a line
################
function getSettingValue($val){
	$val = trim(strstr($val,"="));
	return trim(str_replace('"','',substr($val,1,strpos($val,";")-1)));
}

################
## Function permits to check if a column exists, and if not to add it
################
function add_column_if_not_exist($db, $column, $column_attr = "VARCHAR( 255 ) NULL" ){
	$exists = false;
	$columns = mysql_query("show columns from $db");
	while($c = mysql_fetch_assoc($columns)){
		if($c['Field'] == $column){
			$exists = true;
			break;
		}
	}
	if(!$exists){
		return mysql_query("ALTER TABLE `$db` ADD `$column`  $column_attr");
	}
}

function table_exists($tablename, $database = false) {

	if(!$database) {
		$res = mysql_query("SELECT DATABASE()");
		$database = mysql_result($res, 0);
	}

	$res = mysql_query("
	    SELECT COUNT(*) AS count
        FROM information_schema.tables
        WHERE table_schema = '$database'
        AND table_name = '$tablename'
    ");

	return mysql_result($res, 0) == 1;

}



if ( isset($_POST['type']) ){
	switch( $_POST['type'] ){
		case "step1":
			// erase session table
			$_SESSION = array();
			setcookie('pma_end_session');
			session_destroy();

			$abspath = str_replace('\\','/',$_POST['abspath']);
			$_SESSION['abspath'] = $abspath;
			if ( substr($abspath,strlen($abspath)-1) == "/" ) $abspath = substr($abspath,0,strlen($abspath)-1);
			$ok_writable = true;
			$ok_extensions = true;
			$txt = "";
			$x=1;
			$tab = array($abspath."/includes/settings.php",$abspath."/install/",$abspath."/includes/",$abspath."/files/",$abspath."/upload/");
			foreach($tab as $elem){
				if ( is_writable($elem) )
					$txt .= '<span style=\"padding-left:30px;font-size:13pt;\">'.$elem.'&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
				else{
					$txt .= '<span style=\"padding-left:30px;font-size:13pt;\">'.$elem.'&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
					$ok_writable = false;
				}
				$x++;
			}

			if (!extension_loaded('mcrypt')) {
				$ok_extensions = false;
				$txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mcrypt\"&nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
			}else{
				$txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP extension \"mcrypt\"&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
			}
			if (version_compare(phpversion(), '5.3.0', '<')) {
        		$ok_version = false;
        		$txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP version '.phpversion().' is not OK (minimum is 5.3.0) &nbsp;&nbsp;<img src=\"images/minus-circle.png\"></span><br />';
        	}else{
        		$txt .= '<span style=\"padding-left:30px;font-size:13pt;\">PHP version '.phpversion().' is OK&nbsp;&nbsp;<img src=\"images/tick-circle.png\"></span><br />';
        	}

			if ( $ok_writable == true && $ok_extensions == true ) {
				echo 'document.getElementById("but_next").disabled = "";';
				echo 'document.getElementById("res_step1").innerHTML = "Elements are OK.";';
				echo 'gauge.modify($("pbar"),{values:[0.25,1]});';
			}else{
				echo 'document.getElementById("but_next").disabled = "disabled";';
				echo 'document.getElementById("res_step1").innerHTML = "Correct the shown errors and click on button Launch to refresh";';
				echo 'gauge.modify($("pbar"),{values:[0.25,1]});';
			}

			//get infos from SETTINGS.PHP file
			$filename = "../includes/settings.php";
			$events = "";
			if (file_exists($filename)) {
				//copy some constants from this existing file
				$settings_file = file($filename);
				while(list($key,$val) = each($settings_file)) {
					if (substr_count($val,'charset')>0) $_SESSION['charset'] = getSettingValue($val);
					else if (substr_count($val,'@define(')>0) $_SESSION['encrypt_key'] = substr($val,17,strpos($val,"')")-17);
					else if (substr_count($val,'$smtp_server')>0) $_SESSION['smtp_server'] = getSettingValue($val);
					else if (substr_count($val,'$smtp_auth')>0) $_SESSION['smtp_auth'] = getSettingValue($val);
					else if (substr_count($val,'$smtp_auth_username')>0) $_SESSION['smtp_auth_username'] = getSettingValue($val);
					else if (substr_count($val,'$smtp_auth_password')>0) $_SESSION['smtp_auth_password'] = getSettingValue($val);
					else if (substr_count($val,'$email_from')>0) $_SESSION['email_from'] = getSettingValue($val);
					else if (substr_count($val,'$email_from_name')>0) $_SESSION['email_from_name'] = getSettingValue($val);
					else if (substr_count($val,'$server')>0) $_SESSION['server'] = getSettingValue($val);
					else if (substr_count($val,'$user')>0) $_SESSION['user'] = getSettingValue($val);
					else if (substr_count($val,'$pass')>0) $_SESSION['pass'] = getSettingValue($val);
					else if (substr_count($val,'$database')>0) $_SESSION['database'] = getSettingValue($val);
					else if (substr_count($val,'$pre')>0) $_SESSION['pre'] = getSettingValue($val);
				}
			}

			echo 'document.getElementById("res_step1").innerHTML = "'.$txt.'";';
			echo 'document.getElementById("loader").style.display = "none";';
			break;

			#==========================
		case "step2":
			$res = "";
			$db_password = str_replace(" ","+",urldecode($_POST['db_password']));
			// connexion
			if ( @mysql_connect($_POST['db_host'],$_POST['db_login'],$db_password) ){
				$db_tmp = mysql_connect($_POST['db_host'], $_POST['db_login'], $db_password);
				if ( @mysql_select_db($_POST['db_bdd'],$db_tmp) ){
					echo 'gauge.modify($("pbar"),{values:[0.50,1]});';
					$res = "Connection is successfull";
					echo 'document.getElementById("but_next").disabled = "";';

					//What CPM version
					if(@mysql_query("SELECT valeur FROM ".$_POST['tbl_prefix']."misc WHERE type='admin' AND intitule = 'cpassman_version'")){
						$tmp_result = mysql_query("SELECT valeur FROM ".$_POST['tbl_prefix']."misc WHERE type='admin' AND intitule = 'cpassman_version'");
						$cpm_version = mysql_fetch_row($tmp_result);
						echo 'document.getElementById("actual_cpm_version").value = "'.$cpm_version[0].'";';
					}else{
						echo 'document.getElementById("actual_cpm_version").value = "0";';
					}

					//Get some infos from DB
					if(@mysql_fetch_row(mysql_query("SELECT valeur FROM ".$_POST['tbl_prefix']."misc WHERE type='admin' AND intitule = 'utf8_enabled'"))){
						$cpm_is_utf8 = mysql_fetch_row(mysql_query("SELECT valeur FROM ".$_POST['tbl_prefix']."misc WHERE type='admin' AND intitule = 'utf8_enabled'"));
						echo 'document.getElementById("cpm_is_utf8").value = "'.$cpm_is_utf8[0].'";';
						$_SESSION['utf8_enabled'] = $cpm_is_utf8[0];
					}else{
						echo 'document.getElementById("cpm_is_utf8").value = "0";';
						$_SESSION['utf8_enabled'] = 0;
					}
				}else{
					echo 'gauge.modify($("pbar"),{values:[0.50,1]});';
					$res = "Impossible to get connected to database. Error is ".mysql_error();
					echo 'document.getElementById("but_next").disabled = "disabled";';
				}
			}else{
				echo 'gauge.modify($("pbar"),{values:[0.50,1]});';
				$res = "Impossible to get connected to server. Error is ".mysql_error();
				echo 'document.getElementById("but_next").disabled = "disabled";';
			}

			echo 'document.getElementById("res_step2").innerHTML = "'.$res.'";';
			echo 'document.getElementById("loader").style.display = "none";';
			break;

			#==========================
		case "step3":
			@mysql_connect($_SESSION['db_host'],$_SESSION['db_login'],$_SESSION['db_pw']);
			@mysql_select_db($_SESSION['db_bdd']);
			$db_tmp = mysql_connect($_SESSION['db_host'], $_SESSION['db_login'], $_SESSION['db_pw']);
			mysql_select_db($_SESSION['db_bdd'],$db_tmp);
			$status = "";

			//rename tables
			if (isset($_POST['prefix_before_convert']) && $_POST['prefix_before_convert'] == "true") {
				$tables =mysql_query('SHOW TABLES');
				while($table = mysql_fetch_row($tables)){
					if (table_exists("old_".$table[0]) != 1 && substr($table[0],0,4) != "old_"){
						mysql_query("CREATE TABLE old_".$table[0]." LIKE ".$table[0]);
						mysql_query("INSERT INTO old_".$table[0]." SELECT * FROM ".$table[0]);
					}
				}
			}

			//convert database
			mysql_query("ALTER DATABASE `".$_SESSION['db_bdd']."` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");

			//convert tables
			$res = mysql_query("SHOW TABLES FROM `".$_SESSION['db_bdd']."`");
			while($table = mysql_fetch_row($res)) {
				if (substr($table[0],0,4) != "old_") {
					mysql_query("ALTER TABLE ".$_SESSION['db_bdd'].".`{$table[0]}` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");
					mysql_query("ALTER TABLE".$_SESSION['db_bdd'].".`{$table[0]}` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
				}
			}


			echo 'document.getElementById("res_step3").innerHTML = "Done!";';
			echo 'document.getElementById("loader").style.display = "none";';
			echo 'document.getElementById("but_next").disabled = "";';
			echo 'document.getElementById("but_launch").disabled = "disabled";';

			mysql_close($db_tmp);
			break;

			#==========================
		case "step4":
			//include librairies
			require_once ("../sources/NestedTree.class.php");

			//Build tree
			$tree = new NestedTree($_SESSION['tbl_prefix'].'nested_tree', 'id', 'parent_id', 'title');

			// Database
			$res = "";

			@mysql_connect($_SESSION['db_host'],$_SESSION['db_login'],$_SESSION['db_pw']);
			@mysql_select_db($_SESSION['db_bdd']);
			$db_tmp = mysql_connect($_SESSION['db_host'], $_SESSION['db_login'], $_SESSION['db_pw']);
			mysql_select_db($_SESSION['db_bdd'],$db_tmp);

			## Populate table MISC
			$val = array(
				array('admin', 'max_latest_items', '10',0),
				array('admin', 'enable_favourites', '1',0),
				array('admin', 'show_last_items', '1',0),
				array('admin', 'enable_pf_feature', '0',0),
				array('admin', 'menu_type', 'context',0),
				array('admin', 'log_connections', '0',0),
				array('admin', 'time_format', 'H:i:s',0),
				array('admin', 'date_format', 'd/m/Y',0),
				array('admin', 'duplicate_folder', '0',0),
				array('admin', 'duplicate_item', '0',0),
				array('admin', 'number_of_used_pw', '3',0),
				array('admin', 'manager_edit', '1',0),
				array('admin', 'cpassman_dir', '',0),
				array('admin', 'cpassman_url', '',0),
				array('admin', 'favicon', '',0),
				array('admin', 'activate_expiration', '0',0),
				array('admin','pw_life_duration','30',0),
				array('admin','maintenance_mode','1',1),
				array('admin','cpassman_version',$k['version'],1),
				array('admin','ldap_mode','0',0),
				array('admin','richtext',0,0),
				array('admin','allow_print',0,0),
				array('admin','show_description',1,0),
				array('admin','anyone_can_modify',0,0),
				array('admin','nb_bad_authentication',0,0),
				array('admin','restricted_to',0,0),
				array('admin','restricted_to_roles',0,0),
				array('admin','utf8_enabled',1,0),
				array('admin','custom_logo','',0),
				array('admin','custom_login_text','',0),
				array('admin', 'log_accessed', '1',1),
				array('admin', 'default_language', 'english',0),
				array('admin','send_stats', empty($_SESSION['send_stats']) ? '0' : $_SESSION['send_stats'],1),
				array('admin', 'send_mail_on_user_login', '0', 0),
				array('cron', 'sending_emails', '0', 0),
				array('admin', 'nb_items_by_query', 'auto', 0)
			);
			$res1 = "na";
			foreach($val as $elem){
				//Check if exists before inserting
				$res_tmp = mysql_fetch_row(mysql_query("SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."misc WHERE type='".$elem[0]."' AND intitule='".$elem[1]."'"));
				if ( $res_tmp[0] == 0 ){
					$res1 = mysql_query("INSERT INTO `".$_SESSION['tbl_prefix']."misc` (`type`, `intitule`, `valeur`) VALUES ('".$elem[0]."', '".$elem[1]."', '".$elem[2]."');");
					if ( !$res1 ) break;
				}else{
					// Force update for some settings
					if ( $elem[3] == 1 ){
						mysql_query("UPDATE `".$_SESSION['tbl_prefix']."misc` SET `valeur` = '".$elem[2]."' WHERE type = 'admin' AND intitule = '".$elem[1]."'");
					}
				}
			}

			if ( $res1 || $res1 == "na" ){
				echo 'document.getElementById("tbl_1").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears when inserting datas!";';
				echo 'document.getElementById("tbl_1").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## Alter ITEMS table
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."items","anyone_can_modify","TINYINT(1) NOT NULL DEFAULT '0'");

			## Alter USERS table
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","favourites","VARCHAR(300)");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","latest_items","VARCHAR(300)");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","personal_folder","INT(1) NOT NULL DEFAULT '0'");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","disabled","TINYINT(1) NOT NULL DEFAULT '0'");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","no_bad_attempts","TINYINT(1) NOT NULL DEFAULT '0'");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","can_create_root_folder","TINYINT(1) NOT NULL DEFAULT '0'");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","read_only","TINYINT(1) NOT NULL DEFAULT '0'");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","timestamp","VARCHAR(30) NOT NULL DEFAULT '0'");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."users","user_language","VARCHAR(30) NOT NULL DEFAULT 'english'");
			echo 'document.getElementById("tbl_2").innerHTML = "<img src=\"images/tick.png\">";';

			## Alter nested_tree table
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."nested_tree","personal_folder","TINYINT(1) NOT NULL DEFAULT '0'");
			$res2 = add_column_if_not_exist($_SESSION['tbl_prefix']."nested_tree","renewal_period","TINYINT(4) NOT NULL DEFAULT '0'");
			echo 'document.getElementById("tbl_5").innerHTML = "<img src=\"images/tick.png\">";';

			#to 1.08
			//include('upgrade_db_1.08.php');

			## TABLE TAGS
			$res8 = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."tags` (
                  `id` int(12) NOT NULL AUTO_INCREMENT,
                  `tag` varchar(30) NOT NULL,
                  `item_id` int(12) NOT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `id` (`id`)
                );");
			if ( $res8 ){
				echo 'document.getElementById("tbl_3").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table TAGS!";';
				echo 'document.getElementById("tbl_3").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## TABLE LOG_SYSTEM
			$res8 = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."log_system` (
                  `id` int(12) NOT NULL AUTO_INCREMENT,
                  `type` varchar(20) NOT NULL,
                  `date` varchar(30) NOT NULL,
                  `label` text NOT NULL,
                  `qui` varchar(30) NOT NULL,
                  PRIMARY KEY (`id`)
                );");
			if ( $res8 ){
				echo 'document.getElementById("tbl_4").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table LOG_SYSTEM!";';
				echo 'document.getElementById("tbl_4").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## TABLE 10 - FILES
			$res9 = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."files` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `id_item` int(11) NOT NULL,
                `name` varchar(100) NOT NULL,
                `size` int(10) NOT NULL,
                `extension` varchar(10) NOT NULL,
                `type` varchar(50) NOT NULL,
                `file` varchar(50) NOT NULL,
                PRIMARY KEY (`id`)
                );");
			if ( $res9 ){
				echo 'document.getElementById("tbl_6").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table FILES!";';
				echo 'document.getElementById("tbl_6").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}
			mysql_query("ALTER TABLE `".$_SESSION['tbl_prefix']."files` CHANGE id id INT(11) AUTO_INCREMENT PRIMARY KEY;");
			mysql_query("ALTER TABLE `".$_SESSION['tbl_prefix']."files` CHANGE name name VARCHAR(100) NOT NULL;");

			## TABLE CACHE
			mysql_query("DROP TABLE IF EXISTS `".$_SESSION['tbl_prefix']."cache`");
			$res8 = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."cache` (
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
                );");
			if ( $res8 ){
				//ADD VALUES
				$sql = "SELECT *
						FROM ".$_SESSION['tbl_prefix']."items AS i
		                INNER JOIN ".$_SESSION['tbl_prefix']."log_items AS l ON (l.id_item = i.id)
		                AND l.action = 'at_creation'
                        WHERE i.inactif=0";
				$rows = mysql_query($sql);
				while( $reccord = mysql_fetch_array($rows)){
					//Get all TAGS
					$tags = "";
					$items_res = mysql_query("SELECT tag FROM ".$_SESSION['tbl_prefix']."tags WHERE item_id=".$reccord['id']) or die(mysql_error());
					$item_tags = mysql_fetch_array($items_res);
					if ( !empty($item_tags) )
						foreach( $item_tags as $item_tag ){
							if ( !empty($item_tag['tag']))
								$tags .= $item_tag['tag']. " ";
						}

					//form id_tree to full foldername
					$folder = "";
					$arbo = $tree->getPath($reccord['id_tree'], true);
					foreach($arbo as $elem){
						$folder .= htmlspecialchars(stripslashes($elem->title), ENT_QUOTES)." > ";
					}

					//store data
					mysql_query(
					"INSERT INTO ".$_SESSION['tbl_prefix']."cache
                        VALUES (
                            '".$reccord['id']."',
                            '".$reccord['label']."',
                            '".$reccord['description']."',
                            '".$tags."',
                            '".$reccord['id_tree']."',
                            '".$reccord['perso']."',
                            '".$reccord['restricted_to']."',
                            '".$reccord['login']."',
                            '".$folder."',
                            '".$reccord['id_user']."'
                        )"
					);
				}
				echo 'document.getElementById("tbl_7").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table CACHE!";';
				echo 'document.getElementById("tbl_7").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			/*
			   *  Change table FUNCTIONS
			   *  By 2 tables ROLES
			*/
			$res9 = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."roles_title` (
                `id` int(12) NOT NULL,
                `title` varchar(50) NOT NULL,
                `allow_pw_change` TINYINT(1) NOT NULL DEFAULT '0'
                );");
			add_column_if_not_exist($_SESSION['tbl_prefix']."roles_title","allow_pw_change","TINYINT(1) NOT NULL DEFAULT '0'");
			add_column_if_not_exist($_SESSION['tbl_prefix']."roles_title","complexity","INT(5) NOT NULL DEFAULT '0'");

			$res10 = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."roles_values` (
                `role_id` int(12) NOT NULL,
                `folder_id` int(12) NOT NULL
                );");
			if (table_exists($_SESSION['tbl_prefix']."functions")) {
				$table_function_exists = true;
			}else{
				$table_function_exists = false;
			}
			if ( $res9 && $res10 && $table_function_exists == true){
				//Get data from tables FUNCTIONS and populate new ROLES tables
				$rows = mysql_query("SELECT * FROM ".$_SESSION['tbl_prefix']."functions");
				while( $reccord = mysql_fetch_array($rows)){
					//Add new role title
					mysql_query(
					"INSERT INTO ".$_SESSION['tbl_prefix']."roles_title
                        VALUES (
                            '".$reccord['id']."',
                            '".$reccord['title']."'
                        )"
					);

					//Add each folder in roles_values
					foreach(explode(';', $reccord['groupes_visibles']) as $folder_id){
						if (!empty($folder_id)) {
							mysql_query(
							"INSERT INTO ".$_SESSION['tbl_prefix']."roles_values
								VALUES (
								'".$reccord['id']."',
								'".$folder_id."'
								)"
							);
						}
					}
				}

				//Now alter table roles_title in order to create a primary index
				mysql_query("ALTER TABLE `".$_SESSION['tbl_prefix']."roles_title` ADD PRIMARY KEY(`id`)");
				mysql_query("ALTER TABLE `".$_SESSION['tbl_prefix']."roles_title` CHANGE `id` `id` INT( 12 ) NOT NULL AUTO_INCREMENT ");
				add_column_if_not_exist($_SESSION['tbl_prefix']."roles_title","allow_pw_change","TINYINT(1) NOT NULL DEFAULT '0'");

				//Drop old table
				mysql_query("DROP TABLE ".$_SESSION['tbl_prefix']."functions");

				echo 'document.getElementById("tbl_9").innerHTML = "<img src=\"images/tick.png\">";';
			}else if($table_function_exists == false) {
				echo 'document.getElementById("tbl_9").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on tables ROLES creation!";';
				echo 'document.getElementById("tbl_9").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## TABLE KB
			$res = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb` (
					`id` int(12) NOT NULL AUTO_INCREMENT,
					`category_id` int(12) NOT NULL,
					`label` varchar(200) NOT NULL,
					`description` text NOT NULL,
					`author_id` int(12) NOT NULL,
					`anyone_can_modify` tinyint(1) NOT NULL DEFAULT '0',
					PRIMARY KEY (`id`)
                );");
			if ( $res ){
				echo 'document.getElementById("tbl_10").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table KB!";';
				echo 'document.getElementById("tbl_10").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## TABLE KB_CATEGORIES
			$res = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb_categories` (
					`id` int(12) NOT NULL AUTO_INCREMENT,
					`category` varchar(50) NOT NULL,
					PRIMARY KEY (`id`)
                );");
			if ( $res ){
				echo 'document.getElementById("tbl_11").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table KB_CATEGORIES!";';
				echo 'document.getElementById("tbl_11").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## TABLE KB_ITEMS
			$res = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."kb_items` (
                `kb_id` tinyint(12) NOT NULL,
                `item_id` tinyint(12) NOT NULL
                );");
			if ( $res ){
				echo 'document.getElementById("tbl_12").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table KB_ITEMS!";';
				echo 'document.getElementById("tbl_12").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## TABLE restriction_to_roles
			$res = mysql_query("
			    CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."restriction_to_roles` (
                `role_id` tinyint(12) NOT NULL,
                `item_id` tinyint(12) NOT NULL
                ) CHARSET=utf8;");
			if ( $res ){
				echo 'document.getElementById("tbl_13").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table restriction_to_roles!";';
				echo 'document.getElementById("tbl_13").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## TABLE keys
			$res = mysql_query("
			    CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."keys` (
                `table` varchar(25) NOT NULL,
                `id` int(20) NOT NULL,
                `rand_key` varchar(25) NOT NULL
                ) CHARSET=utf8;");

			$res_tmp = mysql_fetch_row(mysql_query("SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."keys"));
			if ( $res && $res_tmp[0] == 0 ){
				echo 'document.getElementById("tbl_14").innerHTML = "<img src=\"images/tick.png\">";';

				//increase size of PW field in ITEMS table
				mysql_query("ALTER TABLE ".$_SESSION['tbl_prefix']."items MODIFY pw VARCHAR(150)");

				//Populate table KEYS
				//create all keys for all items
				$rows = mysql_query("SELECT * FROM ".$_SESSION['tbl_prefix']."items WHERE perso = 0");
				while($reccord = mysql_fetch_array($rows)){
					if(!empty($reccord['pw'])){
						//get pw
						$pw = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, SALT, base64_decode($reccord['pw']), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));

						//generate random key
						$random_key = substr(md5(rand().rand()), 0, 15);

						//Store generated key
						mysql_query("INSERT INTO ".$_SESSION['tbl_prefix']."keys VALUES('items', '".$reccord['id']."', '".$random_key."') ");

						//encrypt
						$encrypted_pw = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, SALT, $random_key.$pw, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));

						//update pw in ITEMS table
						mysql_query("UPDATE ".$_SESSION['tbl_prefix']."items SET pw = '".$encrypted_pw."' WHERE id='".$reccord['id']."'") or die(mysql_error());
					}
				}
				/*TODO
				//create all keys for all users
				$rows = mysql_query("SELECT id, pw FROM ".$_SESSION['tbl_prefix']."users");
				while($reccord = mysql_fetch_array($rows)){
					if(!empty($reccord['pw'])){
						//get pw
						$pw = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, SALT, base64_decode($reccord['pw']), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));

						//generate random key
						$random_key = substr(md5(rand().rand()), 0, rand(14,29));

						//Store generated key
						mysql_query("INSERT INTO ".$_SESSION['tbl_prefix']."keys VALUES('users', '".$reccord['id']."', '".$random_key."') ");

						//encrypt
						$encrypted_pw = trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, SALT, $random_key.$pw, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));

						//update pw in ITEMS table
						mysql_query("UPDATE ".$_SESSION['tbl_prefix']."users SET pw = '".$encrypted_pw."' WHERE id='".$reccord['id']."'") or die(mysql_error());
					}
				}
				*/
				echo 'document.getElementById("tbl_15").innerHTML = "<img src=\"images/tick.png\">";';

			}else{
				echo 'document.getElementById("tbl_14").innerHTML = "<img src=\"images/tick.png\">";';
				echo 'document.getElementById("tbl_15").innerHTML = "<img src=\"images/tick.png\">";';
			}

			## TABLE Languages
			$res = mysql_query("
			    CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."languages` (
                `id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`name` VARCHAR( 50 ) NOT NULL ,
				`label` VARCHAR( 50 ) NOT NULL ,
				`code` VARCHAR( 10 ) NOT NULL ,
				`flag` VARCHAR( 30 ) NOT NULL
                ) CHARSET=utf8;");

			$res_tmp = mysql_fetch_row(mysql_query("SELECT COUNT(*) FROM ".$_SESSION['tbl_prefix']."languages"));
			if ( $res && $res_tmp[0] == 0 ){
				mysql_query("
	                INSERT IGNORE INTO `".$_SESSION['tbl_prefix']."languages` (`id`, `name`, `label`, `code`, `flag`) VALUES
	                ('', 'french', 'French' , 'fr', 'fr.png'),
	                ('', 'english', 'English' , 'us', 'us.png'),
	                ('', 'spanish', 'Spanish' , 'es', 'es.png'),
	                ('', 'german', 'German' , 'de', 'de.png'),
	                ('', 'czech', 'Czech' , 'cz', 'cz.png'),
	                ('', 'russian', 'Russian' , 'ru', 'ru.png'),
	                ('', 'hungarian', 'Hungarian' , 'hu', 'hu.png'),
	                ('', 'turkish', 'Turkish' , 'tr', 'tr.png'),
	                ('', 'norwegian', 'Norwegian' , 'no', 'no.png'),
	                ('', 'japanese', 'Japanese' , 'ja', 'ja.png'),
	                ('', 'portuguese', 'Portuguese' , 'pr', 'pr.png');");
			}
			if ( $res ){
				echo 'document.getElementById("tbl_16").innerHTML = "<img src=\"images/tick.png\">";';
			}else{
				echo 'document.getElementById("res_step4").innerHTML = "An error appears on table LANGUAGES!";';
				echo 'document.getElementById("tbl_13").innerHTML = "<img src=\"images/exclamation-red.png\">";';
				echo 'document.getElementById("loader").style.display = "none";';
				mysql_close($db_tmp);
				break;
			}

			## TABLE EMAILS
			$res = mysql_query("
                CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."emails` (
                `timestamp` INT( 30 ) NOT NULL ,
				`subject` VARCHAR( 255 ) NOT NULL ,
				`body` TEXT NOT NULL ,
				`receivers` VARCHAR( 255 ) NOT NULL ,
				`status` VARCHAR( 30 ) NOT NULL
                ) CHARSET=utf8;");



			//CLEAN UP ITEMS TABLE
			$allowed_tags = '<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul><blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>';
			$clean_res = mysql_query("SELECT id,description FROM `".$_SESSION['tbl_prefix']."items`");
			while ($clean_data = mysql_fetch_array($clean_res)) {
				mysql_query("UPDATE `".$_SESSION['tbl_prefix']."items` SET description = '".strip_tags($clean_data['description'], $allowed_tags)."' WHERE id = ".$clean_data['id']);
			}

			//Encrypt passwords in log_items
			$res_tmp = mysql_fetch_row(mysql_query("SELECT COUNT(*) FROM ".$pre."misc WHERE type = 'update' AND intitule = 'encrypt_pw_in_log_items' AND valeur = 1"));
			if ( $res_tmp[0] == 0 ){
				require_once '../includes/libraries/crypt/aes.class.php';     // AES PHP implementation
				require_once '../includes/libraries/crypt/aesctr.class.php';  // AES Counter Mode implementation
				$tmp_res = mysql_query("SELECT * FROM ".$pre."log_items WHERE action = 'at_modification' AND raison LIKE 'at_pw %'");
				while ($tmp_data = mysql_fetch_array($tmp_res)) {
					$reason = explode(':',$tmp_data['raison']);
					$text = AesCtr::encrypt(trim($reason[1]), $_SESSION['encrypt_key'], 256);
					//mysql_query("UPDATE ".$pre."log_items SET raison = 'at_pw : ".$text."' WHERE id_item = ".$tmp_data['id_item']." AND date = ".$tmp_data['date']." AND id_user = ".$tmp_data['id_user']." AND action = ".$tmp_data['action']) or die(mysql_error());
				}
				mysql_query("INSERT INTO `".$_SESSION['tbl_prefix']."misc` VALUES ('update', 'encrypt_pw_in_log_items',1)");
			}

			/* Unlock this step */
			echo 'gauge.modify($("pbar"),{values:[0.75,1]});';
			echo 'document.getElementById("but_next").disabled = "";';
			echo 'document.getElementById("but_launch").disabled = "disabled";';
			echo 'document.getElementById("res_step4").innerHTML = "Database has been populated";';
			echo 'document.getElementById("loader").style.display = "none";';
			mysql_close($db_tmp);
			break;

			//=============================
		case "step5":
			$filename = "../includes/settings.php";
			$events = "";
			if (file_exists($filename)) {
				//Do a copy of the existing file
				if ( !copy($filename, $filename.'.'.date("Y_m_d",mktime(0,0,0,date('m'),date('d'),date('y')))) ) {
					echo 'document.getElementById("res_step5").innerHTML = "Setting.php file already exists and cannot be renamed. Please do it by yourself and click on button Launch.";';
					echo 'document.getElementById("loader").style.display = "none";';
					break;
				}else{
					$events .= "The file $filename already exist. A copy has been created.<br />";
					unlink($filename);
				}

				$fh = fopen($filename, 'w');

				//prepare smtp_auth variable
				if (empty($_SESSION['smtp_auth'])) $_SESSION['smtp_auth'] = 'false';
				if (empty($_SESSION['smtp_auth_username'])) $_SESSION['smtp_auth_username'] = 'false';
				if (empty($_SESSION['smtp_auth_password'])) $_SESSION['smtp_auth_password'] = 'false';
				if (empty($_SESSION['email_from_name'])) $_SESSION['email_from_name'] = 'false';

				fwrite($fh, utf8_encode("<?php
global \$lang, \$txt, \$k, \$chemin_passman, \$url_passman, \$pw_complexity, \$mngPages;
global \$smtp_server, \$smtp_auth, \$smtp_auth_username, \$smtp_auth_password, \$email_from,\$email_from_name;
global \$server, \$user, \$pass, \$database, \$pre, \$db;

@define('SALT', '". $_SESSION['encrypt_key'] ."'); //Define your encryption key => NeverChange it once it has been used !!!!!

### EMAIL PROPERTIES ###
\$smtp_server = '".str_replace("'", "", $_SESSION['smtp_server'])."';
\$smtp_auth = '".str_replace("'", "\'", $_SESSION['smtp_auth'])."'; //false or true
\$smtp_auth_username = '".str_replace("'", "\'", $_SESSION['smtp_auth_username'])."';
\$smtp_auth_password = '".str_replace("'", "\'", $_SESSION['smtp_auth_password'])."';
\$email_from = '".str_replace("'", "", $_SESSION['email_from'])."';
\$email_from_name = '".str_replace("'", "", $_SESSION['email_from_name'])."';

### DATABASE connexion parameters ###
\$server = \"". $_SESSION['db_host'] ."\";
\$user = \"". $_SESSION['db_login'] ."\";
\$pass = \"". str_replace("$", "\\$",$_SESSION['db_pw']) ."\";
\$database = \"". $_SESSION['db_bdd'] ."\";
\$pre = \"". $_SESSION['tbl_prefix'] ."\";

@date_default_timezone_set(\$_SESSION['settings']['timezone']);

?>"));

				fclose($fh);
				echo 'gauge.modify($("pbar"),{values:[1,1]});';
				echo 'document.getElementById("but_next").disabled = "";';
				echo 'document.getElementById("res_step5").innerHTML = "Setting.php file has created.";';
				echo 'document.getElementById("loader").style.display = "none";';
				echo 'document.getElementById("but_launch").disabled = "disabled";';

			}else{
				//settings.php file doesn't exit => ERROR !!!!
				echo 'document.getElementById("res_step5").innerHTML = "<img src=\"../includes/images/error.png\">&nbsp;Setting.php file doesn\'t exist! Upgrade can\'t continue without this file.<br />Please copy your existing settings.php into the \"includes\" folder of your cpassman installation ";';
				echo 'document.getElementById("loader").style.display = "none";';
			}

			break;
	}
}
?>