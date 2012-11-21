DROP TABLE teampass_automatic_del;

CREATE TABLE `teampass_automatic_del` (
  `item_id` int(11) NOT NULL,
  `del_enabled` tinyint(1) NOT NULL,
  `del_type` tinyint(1) NOT NULL,
  `del_value` varchar(35) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_cache;

CREATE TABLE `teampass_cache` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_cache VALUES("1","item 1","test","test ","1","0","","me","F1","3");
INSERT INTO teampass_cache VALUES("2","item 2","bla bla bla<br />pouf","","1","0","","lui","F1","2");
INSERT INTO teampass_cache VALUES("3","u1_1","","","4","1","","","2","2");
INSERT INTO teampass_cache VALUES("4","U1_2","","","10","1","","","2 » U1_F1","2");



DROP TABLE teampass_emails;

CREATE TABLE `teampass_emails` (
  `timestamp` int(30) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `receivers` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_files;

CREATE TABLE `teampass_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_item` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `size` int(10) NOT NULL,
  `extension` varchar(10) NOT NULL,
  `type` varchar(50) NOT NULL,
  `file` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_items;

CREATE TABLE `teampass_items` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `pw` text NOT NULL,
  `url` varchar(250) DEFAULT NULL,
  `id_tree` varchar(10) DEFAULT NULL,
  `perso` tinyint(1) NOT NULL DEFAULT '0',
  `login` varchar(200) DEFAULT NULL,
  `inactif` tinyint(1) NOT NULL DEFAULT '0',
  `restricted_to` varchar(200) NOT NULL,
  `anyone_can_modify` tinyint(1) NOT NULL DEFAULT '0',
  `email` varchar(100) DEFAULT NULL,
  `notification` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

INSERT INTO teampass_items VALUES("1","item 1","test","jYGPKqbhtPIu0QiI6WX8xt+fNikxwa1e/0o+W2x7n+g=","","1","0","me","0","","0","","");
INSERT INTO teampass_items VALUES("2","item 2","bla bla bla<br />pouf","4ybKeFEjd1kBR29T/2L8HVc1QSkiSHmohgcEJ+QF9Vs=","http://www.gg.com","1","0","lui","0","","0","","");
INSERT INTO teampass_items VALUES("3","u1_1","","bLhO1xxgdQPp5COvFLp9wb8c2gkAQGmE5e3+KtENY+0=","","4","1","","0","","0","","");
INSERT INTO teampass_items VALUES("4","U1_2","","+zlgK+NO90ZPti8PBi7pSKSVpbyscputo3SL/Xi+LuU=","","10","1","","0","","0","","");



DROP TABLE teampass_kb;

CREATE TABLE `teampass_kb` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `category_id` int(12) NOT NULL,
  `label` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `author_id` int(12) NOT NULL,
  `anyone_can_modify` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO teampass_kb VALUES("1","1","KB1","bla bla bla","3","1");



DROP TABLE teampass_kb_categories;

CREATE TABLE `teampass_kb_categories` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO teampass_kb_categories VALUES("1","servers");



DROP TABLE teampass_kb_items;

CREATE TABLE `teampass_kb_items` (
  `kb_id` tinyint(12) NOT NULL,
  `item_id` tinyint(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_kb_items VALUES("1","1");



DROP TABLE teampass_keys;

CREATE TABLE `teampass_keys` (
  `table` varchar(25) NOT NULL,
  `id` int(20) NOT NULL,
  `rand_key` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_keys VALUES("items","1","2dce928f79f8bfa");
INSERT INTO teampass_keys VALUES("items","2","3a1369f00e55931");



DROP TABLE teampass_languages;

CREATE TABLE `teampass_languages` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `label` varchar(50) NOT NULL,
  `code` varchar(10) NOT NULL,
  `flag` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

INSERT INTO teampass_languages VALUES("1","french","French","fr","fr.png");
INSERT INTO teampass_languages VALUES("2","english","English","us","us.png");
INSERT INTO teampass_languages VALUES("3","spanish","Spanish","es","es.png");
INSERT INTO teampass_languages VALUES("4","german","German","de","de.png");
INSERT INTO teampass_languages VALUES("5","czech","Czech","cz","cz.png");
INSERT INTO teampass_languages VALUES("6","italian","Italian","it","it.png");
INSERT INTO teampass_languages VALUES("7","russian","Russian","ru","ru.png");
INSERT INTO teampass_languages VALUES("8","turkish","Turkish","tr","tr.png");
INSERT INTO teampass_languages VALUES("9","norwegian","Norwegian","no","no.png");
INSERT INTO teampass_languages VALUES("10","japanese","Japanese","ja","ja.png");
INSERT INTO teampass_languages VALUES("11","portuguese","Portuguese","pr","pr.png");



DROP TABLE teampass_log_items;

CREATE TABLE `teampass_log_items` (
  `id_item` int(8) NOT NULL,
  `date` varchar(50) NOT NULL,
  `id_user` int(8) NOT NULL,
  `action` varchar(250) NOT NULL,
  `raison` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_log_items VALUES("1","1349775172","3","at_creation","");
INSERT INTO teampass_log_items VALUES("1","1349775172","3","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349775294","3","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349775304","3","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349775305","3","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349775377","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349775572","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349775573","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349775648","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349781493","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349781746","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349781783","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349781818","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349781841","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349781896","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349781924","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782053","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782104","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782133","2","at_creation","");
INSERT INTO teampass_log_items VALUES("2","1349782133","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782137","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782171","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782274","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782280","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782459","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782462","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782479","2","at_modification","at_url :  => http://www.gg.com");
INSERT INTO teampass_log_items VALUES("2","1349782479","2","at_modification","at_description");
INSERT INTO teampass_log_items VALUES("2","1349782487","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782495","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782506","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782506","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782506","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782508","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782508","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782508","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782510","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782510","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782510","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349782515","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782519","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782519","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782519","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349782536","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349881351","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349881357","2","at_shown","");
INSERT INTO teampass_log_items VALUES("0","1349881374","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349881374","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349881379","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349881385","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349881385","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349881386","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349881387","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349881580","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("2","1349881584","2","at_shown","");
INSERT INTO teampass_log_items VALUES("0","1349881588","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("2","1349881656","2","at_shown","");
INSERT INTO teampass_log_items VALUES("0","1349881663","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("2","1349881668","2","at_shown","");
INSERT INTO teampass_log_items VALUES("0","1349881828","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("2","1349881831","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349881988","2","at_shown","");
INSERT INTO teampass_log_items VALUES("0","1349881996","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349882216","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("2","1349882265","2","at_shown","");
INSERT INTO teampass_log_items VALUES("0","1349882273","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("2","1349882339","2","at_shown","");
INSERT INTO teampass_log_items VALUES("0","1349882344","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("2","1349882395","2","at_shown","");
INSERT INTO teampass_log_items VALUES("0","1349882515","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("0","1349882608","2","at_manual","at_manual_add : ");
INSERT INTO teampass_log_items VALUES("2","1349882624","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349883227","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349883236","2","at_manual","at_manual_add : blable alal");
INSERT INTO teampass_log_items VALUES("2","1349883374","2","at_manual","at_manual_add : bloue 1");
INSERT INTO teampass_log_items VALUES("2","1349883417","2","at_manual","at_manual_add : plouf");
INSERT INTO teampass_log_items VALUES("2","1349883637","2","at_manual","at_manual_add : plouf");
INSERT INTO teampass_log_items VALUES("2","1349883761","2","at_manual","at_manual_add : yeah");
INSERT INTO teampass_log_items VALUES("2","1349883805","2","at_manual","at_manual_add : yeah");
INSERT INTO teampass_log_items VALUES("2","1349886267","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349886426","2","at_manual","at_manual_add : 23");
INSERT INTO teampass_log_items VALUES("2","1349886436","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349886445","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349886456","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349886463","2","at_modification","at_description");
INSERT INTO teampass_log_items VALUES("2","1349886466","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349886506","2","at_manual","at_manual_add : yopyop");
INSERT INTO teampass_log_items VALUES("2","1349886546","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349973965","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349974177","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349974357","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349974392","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349974422","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1349974751","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349975854","4","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349975871","4","at_manual","manager added this");
INSERT INTO teampass_log_items VALUES("2","1349975874","4","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349975879","4","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349976038","4","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349976086","4","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349976145","4","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1349976369","4","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1352116978","2","at_creation","");
INSERT INTO teampass_log_items VALUES("3","1352116980","2","at_shown","");
INSERT INTO teampass_log_items VALUES("4","1352117090","2","at_creation","");
INSERT INTO teampass_log_items VALUES("4","1352117091","2","at_shown","");



DROP TABLE teampass_log_system;

CREATE TABLE `teampass_log_system` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `date` varchar(30) NOT NULL,
  `label` text NOT NULL,
  `qui` varchar(30) NOT NULL,
  `field_1` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8;

INSERT INTO teampass_log_system VALUES("1","user_connection","1349775090","disconnection","1","");
INSERT INTO teampass_log_system VALUES("2","user_connection","1349775095","connection","3","");
INSERT INTO teampass_log_system VALUES("3","user_connection","1349775111","disconnection","3","");
INSERT INTO teampass_log_system VALUES("4","user_connection","1349775118","connection","3","");
INSERT INTO teampass_log_system VALUES("5","user_connection","1349775362","disconnection","3","");
INSERT INTO teampass_log_system VALUES("6","user_connection","1349775366","connection","2","");
INSERT INTO teampass_log_system VALUES("7","user_connection","1349781482","disconnection","2","");
INSERT INTO teampass_log_system VALUES("8","user_connection","1349781486","connection","2","");
INSERT INTO teampass_log_system VALUES("9","user_connection","1349838141","connection","2","");
INSERT INTO teampass_log_system VALUES("10","error","1349838174","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("11","error","1349838174","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("12","error","1349838179","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("13","error","1349838185","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("14","error","1349838185","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("15","error","1349838186","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("16","error","1349838187","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("17","error","1349838380","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("18","error","1349838388","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("19","error","1349838463","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("20","error","1349838628","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("21","user_connection","1349838777","connection","2","");
INSERT INTO teampass_log_system VALUES("22","error","1349838796","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("23","error","1349839016","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("24","error","1349839073","<b>MySQL Query fail:</b> SELECT * FROM teampass_log_items WHERE id = \'<br />Unknown column \'id\' in \'where clause\'@/t_p/sources/items.queries.php","2","");
INSERT INTO teampass_log_system VALUES("25","user_connection","1349843040","disconnection","2","");
INSERT INTO teampass_log_system VALUES("26","user_connection","1349843060","connection","2","");
INSERT INTO teampass_log_system VALUES("27","user_connection","1349930755","connection","2","");
INSERT INTO teampass_log_system VALUES("28","user_connection","1349931606","disconnection","2","");
INSERT INTO teampass_log_system VALUES("29","user_connection","1349931614","connection","1","");
INSERT INTO teampass_log_system VALUES("30","user_connection","1349932162","disconnection","1","");
INSERT INTO teampass_log_system VALUES("31","user_connection","1349932165","connection","4","");
INSERT INTO teampass_log_system VALUES("32","user_connection","1349941814","disconnection","4","");
INSERT INTO teampass_log_system VALUES("33","user_connection","1349942086","connection","1","");
INSERT INTO teampass_log_system VALUES("34","user_connection","1349942120","disconnection","1","");
INSERT INTO teampass_log_system VALUES("35","user_connection","1349942437","connection","2","");
INSERT INTO teampass_log_system VALUES("36","user_connection","1349942462","disconnection","2","");
INSERT INTO teampass_log_system VALUES("37","user_connection","1349942471","connection","1","");
INSERT INTO teampass_log_system VALUES("38","user_connection","1349942506","disconnection","1","");
INSERT INTO teampass_log_system VALUES("39","user_connection","1349944151","connection","5","");
INSERT INTO teampass_log_system VALUES("40","user_connection","1349944175","connection","5","");
INSERT INTO teampass_log_system VALUES("41","user_connection","1349944197","connection","5","");
INSERT INTO teampass_log_system VALUES("42","user_connection","1349944450","connection","5","");
INSERT INTO teampass_log_system VALUES("43","user_connection","1349944607","disconnection","5","");
INSERT INTO teampass_log_system VALUES("44","user_connection","1349944610","disconnection","","");
INSERT INTO teampass_log_system VALUES("45","user_connection","1350018238","connection","1","");
INSERT INTO teampass_log_system VALUES("46","user_connection","1350018322","disconnection","1","");
INSERT INTO teampass_log_system VALUES("47","user_connection","1350018328","connection","4","");
INSERT INTO teampass_log_system VALUES("48","user_connection","1350019236","disconnection","4","");
INSERT INTO teampass_log_system VALUES("49","user_connection","1350019241","connection","1","");
INSERT INTO teampass_log_system VALUES("50","user_mngt","1350020341","at_user_email_changed::M1@qs.ne","","");
INSERT INTO teampass_log_system VALUES("51","user_mngt","1350020364","at_user_email_changed::M1@qs.ne","","");
INSERT INTO teampass_log_system VALUES("52","user_mngt","1350020407","at_user_email_changed::M1@qs.ne","","");
INSERT INTO teampass_log_system VALUES("53","user_mngt","1350020526","at_user_email_changed:2:U1@sd.net","","");
INSERT INTO teampass_log_system VALUES("54","user_mngt","1350020550","at_user_locked:","","");
INSERT INTO teampass_log_system VALUES("55","user_mngt","1350020583","at_user_locked:","","");
INSERT INTO teampass_log_system VALUES("56","user_mngt","1350020602","at_user_locked:","","");
INSERT INTO teampass_log_system VALUES("57","user_mngt","1350020652","at_user_unlocked:","","");
INSERT INTO teampass_log_system VALUES("58","user_mngt","1350020705","at_user_unlocked:7","","");
INSERT INTO teampass_log_system VALUES("59","user_mngt","1350020810","at_user_unlocked:7","","");
INSERT INTO teampass_log_system VALUES("60","user_mngt","1350021307","at_user_new_login:7","","");
INSERT INTO teampass_log_system VALUES("61","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("62","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("63","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("64","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("65","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("66","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("67","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("68","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("69","error","1350023659","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("70","error","1350023660","<b>MySQL Query fail:</b> <br />Query was empty@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("71","error","1350023685","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("72","error","1350023685","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("73","error","1350023685","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("74","error","1350023685","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("75","error","1350023685","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("76","error","1350023685","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("77","error","1350023686","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("78","error","1350023686","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("79","error","1350023686","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("80","error","1350023686","<b>MySQL Query fail:</b> SELECT login from teampass_users WHERE id=<br />You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'\' at line 1@/t_p/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("81","user_connection","1352116939","connection","2","");
INSERT INTO teampass_log_system VALUES("82","user_connection","1352116992","disconnection","2","");
INSERT INTO teampass_log_system VALUES("83","user_connection","1352117002","connection","1","");
INSERT INTO teampass_log_system VALUES("84","user_connection","1352117032","disconnection","1","");
INSERT INTO teampass_log_system VALUES("85","user_connection","1352117038","connection","2","");
INSERT INTO teampass_log_system VALUES("86","user_connection","1352198230","connection","1","");
INSERT INTO teampass_log_system VALUES("87","user_connection","1352204080","disconnection","1","");
INSERT INTO teampass_log_system VALUES("88","user_connection","1352204352","connection","1","");
INSERT INTO teampass_log_system VALUES("89","admin_action","1352205514","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("90","user_mngt","1352206178","at_user_initial_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("91","admin_action","1352206609","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("92","admin_action","1352206649","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("93","error","1352163617","<b>MySQL Query fail:</b> Resource id #9<br />@/t_p/sources/admin.queries.php","1","");



DROP TABLE teampass_misc;

CREATE TABLE `teampass_misc` (
  `type` varchar(50) NOT NULL,
  `intitule` varchar(100) NOT NULL,
  `valeur` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_misc VALUES("admin","max_latest_items","10");
INSERT INTO teampass_misc VALUES("admin","enable_favourites","1");
INSERT INTO teampass_misc VALUES("admin","show_last_items","1");
INSERT INTO teampass_misc VALUES("admin","enable_pf_feature","1");
INSERT INTO teampass_misc VALUES("admin","log_connections","1");
INSERT INTO teampass_misc VALUES("admin","log_accessed","1");
INSERT INTO teampass_misc VALUES("admin","time_format","H:i:s");
INSERT INTO teampass_misc VALUES("admin","date_format","d/m/Y");
INSERT INTO teampass_misc VALUES("admin","duplicate_folder","1");
INSERT INTO teampass_misc VALUES("admin","duplicate_item","1");
INSERT INTO teampass_misc VALUES("admin","number_of_used_pw","3");
INSERT INTO teampass_misc VALUES("admin","manager_edit","1");
INSERT INTO teampass_misc VALUES("admin","cpassman_dir","c:/nils.laumaille/utils/xampp/htdocs/t_p");
INSERT INTO teampass_misc VALUES("admin","cpassman_url","http://localhost/t_p");
INSERT INTO teampass_misc VALUES("admin","favicon","http://localhost/t_p/favico.ico");
INSERT INTO teampass_misc VALUES("admin","path_to_upload_folder","c:/nils.laumaille/utils/xampp/htdocs/t_p/upload");
INSERT INTO teampass_misc VALUES("admin","url_to_upload_folder","http://localhost/t_p/upload");
INSERT INTO teampass_misc VALUES("admin","path_to_files_folder","c:/nils.laumaille/utils/xampp/htdocs/t_p/files");
INSERT INTO teampass_misc VALUES("admin","url_to_files_folder","http://localhost/t_p/files");
INSERT INTO teampass_misc VALUES("admin","activate_expiration","1");
INSERT INTO teampass_misc VALUES("admin","pw_life_duration","0");
INSERT INTO teampass_misc VALUES("admin","maintenance_mode","0");
INSERT INTO teampass_misc VALUES("admin","cpassman_version","2.1.10");
INSERT INTO teampass_misc VALUES("admin","ldap_mode","0");
INSERT INTO teampass_misc VALUES("admin","richtext","0");
INSERT INTO teampass_misc VALUES("admin","allow_print","1");
INSERT INTO teampass_misc VALUES("admin","show_description","1");
INSERT INTO teampass_misc VALUES("admin","anyone_can_modify","1");
INSERT INTO teampass_misc VALUES("admin","nb_bad_authentication","0");
INSERT INTO teampass_misc VALUES("admin","utf8_enabled","1");
INSERT INTO teampass_misc VALUES("admin","restricted_to","1");
INSERT INTO teampass_misc VALUES("admin","restricted_to_roles","1");
INSERT INTO teampass_misc VALUES("admin","custom_logo","");
INSERT INTO teampass_misc VALUES("admin","custom_login_text","");
INSERT INTO teampass_misc VALUES("admin","default_language","english");
INSERT INTO teampass_misc VALUES("admin","send_stats","0");
INSERT INTO teampass_misc VALUES("admin","send_mail_on_user_login","0");
INSERT INTO teampass_misc VALUES("cron","sending_emails","0");
INSERT INTO teampass_misc VALUES("admin","nb_items_by_query","auto");
INSERT INTO teampass_misc VALUES("admin","enable_delete_after_consultation","1");
INSERT INTO teampass_misc VALUES("admin","enable_personal_saltkey_cookie","0");
INSERT INTO teampass_misc VALUES("admin","personal_saltkey_cookie_duration","31");
INSERT INTO teampass_misc VALUES("admin","email_smtp_server","mail.teampass.net");
INSERT INTO teampass_misc VALUES("admin","email_smtp_auth","true");
INSERT INTO teampass_misc VALUES("admin","email_auth_username","nils");
INSERT INTO teampass_misc VALUES("admin","email_auth_pwd","Aure78");
INSERT INTO teampass_misc VALUES("admin","email_port","25");
INSERT INTO teampass_misc VALUES("admin","email_from","Nils@teampass.net");
INSERT INTO teampass_misc VALUES("admin","email_from_name","Nils TeamPass");
INSERT INTO teampass_misc VALUES("admin","pwd_maximum_length","40");
INSERT INTO teampass_misc VALUES("complex","1","25");
INSERT INTO teampass_misc VALUES("complex","2","25");
INSERT INTO teampass_misc VALUES("complex","3","50");
INSERT INTO teampass_misc VALUES("admin","send_stats_time","0");
INSERT INTO teampass_misc VALUES("admin","ldap_ssl","0");
INSERT INTO teampass_misc VALUES("admin","ldap_tls","0");
INSERT INTO teampass_misc VALUES("admin","enable_kb","1");
INSERT INTO teampass_misc VALUES("admin","copy_to_clipboard_small_icons","1");
INSERT INTO teampass_misc VALUES("admin","enable_user_can_create_folders","1");
INSERT INTO teampass_misc VALUES("admin","enable_send_email_on_user_login","1");
INSERT INTO teampass_misc VALUES("admin","enable_email_notification_on_item_shown","1");
INSERT INTO teampass_misc VALUES("settings","bck_script_filename","bck_cpassman");
INSERT INTO teampass_misc VALUES("settings","bck_script_path","E:/utils/xampp/htdocs/t_p/backups");
INSERT INTO teampass_misc VALUES("settings","bck_script_key","");
INSERT INTO teampass_misc VALUES("admin","insert_manual_entry_item_history","1");
INSERT INTO teampass_misc VALUES("complex","10","25");



DROP TABLE teampass_nested_tree;

CREATE TABLE `teampass_nested_tree` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `nleft` int(11) NOT NULL,
  `nright` int(11) NOT NULL,
  `nlevel` int(11) NOT NULL,
  `bloquer_creation` tinyint(1) NOT NULL DEFAULT '0',
  `bloquer_modification` tinyint(1) NOT NULL DEFAULT '0',
  `personal_folder` tinyint(1) NOT NULL DEFAULT '0',
  `renewal_period` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `nested_tree_parent_id` (`parent_id`),
  KEY `nested_tree_nleft` (`nleft`),
  KEY `nested_tree_nright` (`nright`),
  KEY `nested_tree_nlevel` (`nlevel`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

INSERT INTO teampass_nested_tree VALUES("1","0","F1","17","18","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("2","0","F2","19","22","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("3","2","F2_1","20","21","2","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("4","0","2","3","6","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("5","0","3","7","8","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("6","0","4","9","10","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("7","0","5","11","12","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("8","0","6","13","14","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("9","0","7","15","16","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("10","4","U1_F1","4","5","2","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("11","0","1","1","2","1","0","0","1","0");



DROP TABLE teampass_restriction_to_roles;

CREATE TABLE `teampass_restriction_to_roles` (
  `role_id` int(12) NOT NULL,
  `item_id` int(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_rights;

CREATE TABLE `teampass_rights` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `tree_id` int(12) NOT NULL,
  `fonction_id` int(12) NOT NULL,
  `authorized` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_roles_title;

CREATE TABLE `teampass_roles_title` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `allow_pw_change` tinyint(1) NOT NULL DEFAULT '0',
  `complexity` int(5) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

INSERT INTO teampass_roles_title VALUES("1","users","0","25");
INSERT INTO teampass_roles_title VALUES("2","IT","0","0");
INSERT INTO teampass_roles_title VALUES("3","Sales","0","0");



DROP TABLE teampass_roles_values;

CREATE TABLE `teampass_roles_values` (
  `role_id` int(12) NOT NULL,
  `folder_id` int(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_roles_values VALUES("1","1");
INSERT INTO teampass_roles_values VALUES("1","2");
INSERT INTO teampass_roles_values VALUES("1","3");



DROP TABLE teampass_tags;

CREATE TABLE `teampass_tags` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `tag` varchar(30) NOT NULL,
  `item_id` int(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO teampass_tags VALUES("1","test","1");



DROP TABLE teampass_users;

CREATE TABLE `teampass_users` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `pw` varchar(50) NOT NULL,
  `groupes_visibles` varchar(250) NOT NULL,
  `derniers` text NOT NULL,
  `key_tempo` varchar(100) NOT NULL,
  `last_pw_change` varchar(30) NOT NULL,
  `last_pw` text NOT NULL,
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  `fonction_id` varchar(255) NOT NULL,
  `groupes_interdits` varchar(255) NOT NULL,
  `last_connexion` varchar(30) NOT NULL,
  `gestionnaire` int(11) NOT NULL DEFAULT '0',
  `email` varchar(300) NOT NULL,
  `favourites` varchar(300) NOT NULL,
  `latest_items` varchar(300) NOT NULL,
  `personal_folder` int(1) NOT NULL DEFAULT '0',
  `disabled` tinyint(1) NOT NULL DEFAULT '0',
  `no_bad_attempts` tinyint(1) NOT NULL DEFAULT '0',
  `can_create_root_folder` tinyint(1) NOT NULL DEFAULT '0',
  `read_only` tinyint(1) NOT NULL DEFAULT '0',
  `timestamp` varchar(30) NOT NULL DEFAULT '0',
  `user_language` varchar(30) NOT NULL DEFAULT 'english',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

INSERT INTO teampass_users VALUES("1","admin","Pg4807EK1EB1mUOZ8wlIUub13la4fDsKgj3bC0Du6VE=","","","39CpkJ3vJW7ce1BOsLhDh4jP1rTxqNJWxHXEE37u4ArcUxzdQu","1352160000","","1","","","1352204352","0","","","","1","0","0","0","0","1352206599","english");
INSERT INTO teampass_users VALUES("2","U1","Pg4807EK1EB1mUOZ8wlIUub13la4fDsKgj3bC0Du6VE=","0","","hxCuBgenqSFfGnurx788fo1VBFoiO0HNLnbxGHVuwAH5fe15Cw","1349740800","","0","1","0","1352117038","0","U1@sd.net","","4;3;2;1","1","0","0","0","0","1352117093","english");
INSERT INTO teampass_users VALUES("3","U2","Pg4807EK1EB1mUOZ8wlIUub13la4fDsKgj3bC0Du6VE=","0","","","1349740800","","0","1","0","1349775118","0","U2@net.net","","1","1","0","0","0","0","1349775362","english");
INSERT INTO teampass_users VALUES("4","M1","Pg4807EK1EB1mUOZ8wlIUub13la4fDsKgj3bC0Du6VE=","","","","1349913600","","0","1;2","","1350018328","1","M1@qs.ne","","2;1","1","0","0","0","0","1350019236","english");
INSERT INTO teampass_users VALUES("5","é\'az\"tà","Pg4807EK1EB1mUOZ8wlIUub13la4fDsKgj3bC0Du6VE=","0","","","","","0","0","0","1349944450","0","sd@d.net","","","1","0","0","0","0","1349944607","english");
INSERT INTO teampass_users VALUES("6","U3","Pg4807EK1EB1mUOZ8wlIUub13la4fDsKgj3bC0Du6VE=","0","","","","","0","2","0","","0","u3@sd.net","","","1","0","0","0","1","0","english");
INSERT INTO teampass_users VALUES("7","U4","Pg4807EK1EB1mUOZ8wlIUub13la4fDsKgj3bC0Du6VE=","0","","","","","0","3","0","","0","sd@sds.net","","","1","0","0","0","0","0","english");



