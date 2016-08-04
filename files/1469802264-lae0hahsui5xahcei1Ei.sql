DROP TABLE teampass_api;

CREATE TABLE `teampass_api` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `type` varchar(15) NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `timestamp` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




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
  `id_key` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(250) NOT NULL,
  `description` text NOT NULL,
  `tags` text,
  `url` varchar(255) DEFAULT NULL,
  `id_tree` int(12) NOT NULL,
  `perso` tinyint(1) NOT NULL,
  `restricted_to` varchar(200) DEFAULT NULL,
  `login` varchar(200) DEFAULT NULL,
  `folder` varchar(300) NOT NULL,
  `author` varchar(50) NOT NULL,
  `renewal_period` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_key`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8;

INSERT INTO teampass_cache VALUES("1","1","OXid ZS","","","http://oxid.demo","69","0","","root","ZS e-commerce » Sweet-beast","10000000","0","1469195643");
INSERT INTO teampass_cache VALUES("2","2","Oxid2e3","","","","5","0","","","","10000000","0","1469196473");
INSERT INTO teampass_cache VALUES("3","3","99999999999","","","","7","0","","","","10000000","0","1469197795");
INSERT INTO teampass_cache VALUES("4","4","898989","","","","6","0","","","","10000000","0","1469197849");
INSERT INTO teampass_cache VALUES("5","5","oxiddes","","","","5","0","","","","10000001","0","1469451993");
INSERT INTO teampass_cache VALUES("6","6","oxiddes33","","","","5","0","","","","10000001","0","1469452025");
INSERT INTO teampass_cache VALUES("7","7","oxiddes33ewew","","","http://admin.comxdesss","5","0","","","","10000001","0","1469452075");
INSERT INTO teampass_cache VALUES("8","8","oxideedsrr","","","http://zinit","5","0","","magento","","10000001","0","1469452834");
INSERT INTO teampass_cache VALUES("11","9","testt","","","","32","0","","","","10000010","0","1469632485");
INSERT INTO teampass_cache VALUES("12","10","Plesk Live","","","https://176-28-8-128.kundenadmin.hosteurope.de:8443/","70","0","","admin","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469694784");
INSERT INTO teampass_cache VALUES("13","11","FTP Test","","","ftp://sweet-beast.zinit1.com","70","0","","sweet-beast","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469694871");
INSERT INTO teampass_cache VALUES("14","12","CDN Admin","","","https://cp.maxcdn.com/account","70","0","","zinittester@gmail.com","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469694971");
INSERT INTO teampass_cache VALUES("15","13","Database Test","for developers:<ul><li>host: db.zinit1.com</li><li>user: dev_sweet_beast</li><li>pass: q7rCqfuBQ36USJj4</li><li>database: dev_sweet_beast</li></ul>","","http://db.zinit1.com/phpmyadmin","70","0","","sweet-beast2","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469695139");
INSERT INTO teampass_cache VALUES("16","14","FTP 1C","","","","70","0","","client1c","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469695556");
INSERT INTO teampass_cache VALUES("17","15","ВКонтакте","","","http://vk.com/","70","0","","sweet-beast@mail.ru","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469695717");
INSERT INTO teampass_cache VALUES("18","16","Instagram","","","https://www.instagram.com/","70","0","","sweet7beast","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469695869");
INSERT INTO teampass_cache VALUES("19","17","Одноклассники","","","http://ok.ru/","70","0","","SweetBeast","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469695950");
INSERT INTO teampass_cache VALUES("20","18","FTP storage for backups","","","http://80.237.136.174/","70","0","","ftp12543507-bak","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469696077");
INSERT INTO teampass_cache VALUES("21","19","FTP/SSH Live","Database:<ul><li>host: 176.28.8.128</li><li>user: sweetbea_zinit</li><li>pass: BlTkxO;OlB]1</li><li>database: sweetbea<em>zinit</em></li></ul>","","http://176.28.8.128","70","0","","root","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469696140");
INSERT INTO teampass_cache VALUES("22","20","E-mail sweetbeast7@gmail.com","<strong>текущий пароль:&nbsp;maxxam7777</strong>","","http://google.com","70","0","","sweetbeast7@gmail.com","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469696311");
INSERT INTO teampass_cache VALUES("23","21","E-mail sweetbeast7@gmail.com","","","http://google.com","69","0","","sweetbeast7@gmail.com","ZS e-commerce » Sweet-beast","10000005","0","1469696324");
INSERT INTO teampass_cache VALUES("24","22","E-mail sweetbeast7@gmail.com","<strong>текущий пароль:&nbsp;maxxam7777</strong>","","http://google.com","70","0","","sweetbeast7@gmail.com","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469696343");
INSERT INTO teampass_cache VALUES("25","23","Facebook","","","https://www.facebook.com/","70","0","","+380633364087","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469696606");
INSERT INTO teampass_cache VALUES("26","24","Admin Live/Test","учетка админа на тестовом и на продакшн","","http://sweet-beast.com/admin (sweet-beast.zinit1.com/admin)","46","0","","zinit","","10000005","0","1469696916");
INSERT INTO teampass_cache VALUES("27","25","test123","","","","27","1","","","","10000011","0","1469697124");
INSERT INTO teampass_cache VALUES("28","26","Billing Live","","","http://billing.hostpro.ua","70","0","","max-zr@mail.ru","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469697569");
INSERT INTO teampass_cache VALUES("29","27","SSH Live","","","","32","0","10000004;10000011;","","","10000004","0","1469697837");
INSERT INTO teampass_cache VALUES("30","28","jkbhfdkjsbfas","","","","29","0","10000004;10000011;","","TEst","10000004","0","1469698038");
INSERT INTO teampass_cache VALUES("31","29","Host Live","htaccess:<ul><li>user: sweet-portal</li><li>pass: 1E#A6Z3J-0RloGbCA</li></ul>MySQL:<ul><li>user: petsby</li><li>pass: Xj1zp&amp;64</li><li>base: petsby</li></ul>","","","71","0","","petsby","ZS e-commerce » Petsbynet","10000005","0","1469698111");
INSERT INTO teampass_cache VALUES("32","30","Host test","MySQL:<ul><li>user:&nbsp;petsby</li><li>pass:&nbsp;yfBTob92v20mIjqP</li><li>base:&nbsp;petsby&nbsp;,&nbsp;petsby_dev</li><li>host:&nbsp;db.zinit1.com</li></ul>","","http://petsby.zinit1.com","71","0","","petsby","ZS e-commerce » Petsbynet","10000005","0","1469698188");
INSERT INTO teampass_cache VALUES("33","31","e-mail info@petsby.net","","","http://webmail.petsby.net","71","0","","info@petsby.net","ZS e-commerce » Petsbynet","10000005","0","1469698418");
INSERT INTO teampass_cache VALUES("34","32","siteheart (Online help)","","","https://www.siteheart.com/","71","0","","info@petsby.net","ZS e-commerce » Petsbynet","10000005","0","1469698512");
INSERT INTO teampass_cache VALUES("35","33","Planning poker","","","https://app.planningpoker.com/login","71","0","","piontkovsky.vladislav@gmail.com","ZS e-commerce » Petsbynet","10000005","0","1469698697");
INSERT INTO teampass_cache VALUES("36","34","Admin Test","","","http://www.petsby.zinit1.com/admin/","71","0","","zinit","ZS e-commerce » Petsbynet","10000005","0","1469698755");
INSERT INTO teampass_cache VALUES("37","35","Host Test","sweet-beast-ref<br />MySQL:<ul><li>user: sweet-beast-ref</li><li>base: sweet_beast_ref</li><li>pass: Zmi0DbqjKx2ymJbz</li><li>host: db.zinit1.com</li></ul>","","http://sweet-beast-ref.zinit1.com/","70","0","","sweet-beast-ref","ZS e-commerce » Sweet-beast » Protect","10000005","0","1469698900");
INSERT INTO teampass_cache VALUES("38","36","FTP TEST","","","http://sweet-beast-store.zinit1.com","69","0","","sweet-beast-store","ZS e-commerce » Sweet-beast","10000005","0","1469699117");
INSERT INTO teampass_cache VALUES("39","37","FTP LIVE","","","http://176.28.8.128","69","0","","sweetbeaststore","ZS e-commerce » Sweet-beast","10000005","0","1469699168");
INSERT INTO teampass_cache VALUES("40","38","Database LIVE","DB name: sweet_beast_store","","","69","0","","sweet-beast-stor","ZS e-commerce » Sweet-beast","10000005","0","1469699261");
INSERT INTO teampass_cache VALUES("41","39","e-mail shop@sweet-beast.com","","","","69","0","","shop@sweet-beast.com","ZS e-commerce » Sweet-beast","10000005","0","1469699363");
INSERT INTO teampass_cache VALUES("42","40","Admin TEST","","","http://sweet-beast-store.zinit1.com/admin","69","0","","admin@zinitsolutions.com","ZS e-commerce » Sweet-beast","10000005","0","1469699420");
INSERT INTO teampass_cache VALUES("43","41","SSH Live (new)","","","http://mail.newlogic.ua -p 2225","57","1","","web-developer","","10000016","0","1469708748");
INSERT INTO teampass_cache VALUES("44","42","testitem","","","","69","0","","","ZS e-commerce » Sweet-beast","10000009","0","1469799091");



DROP TABLE teampass_categories;

CREATE TABLE `teampass_categories` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `parent_id` int(12) NOT NULL,
  `title` varchar(255) NOT NULL,
  `level` int(2) NOT NULL,
  `description` text,
  `type` varchar(50) DEFAULT '',
  `order` int(12) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO teampass_categories VALUES("1","0","2222","0","","","1");



DROP TABLE teampass_categories_folders;

CREATE TABLE `teampass_categories_folders` (
  `id_category` int(12) NOT NULL,
  `id_folder` int(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_categories_folders VALUES("1","5");



DROP TABLE teampass_categories_items;

CREATE TABLE `teampass_categories_items` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `field_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `data` text NOT NULL,
  `data_iv` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_emails;

CREATE TABLE `teampass_emails` (
  `timestamp` int(30) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `receivers` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_export;

CREATE TABLE `teampass_export` (
  `id` int(12) NOT NULL,
  `label` varchar(255) NOT NULL,
  `login` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `pw` text NOT NULL,
  `path` varchar(255) NOT NULL
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
  `label` varchar(250) NOT NULL,
  `description` text NOT NULL,
  `pw` text NOT NULL,
  `pw_iv` text NOT NULL,
  `pw_len` int(5) NOT NULL DEFAULT '0',
  `url` varchar(500) DEFAULT NULL,
  `id_tree` varchar(10) DEFAULT NULL,
  `perso` tinyint(1) NOT NULL DEFAULT '0',
  `login` varchar(200) DEFAULT NULL,
  `inactif` tinyint(1) NOT NULL DEFAULT '0',
  `restricted_to` varchar(200) DEFAULT NULL,
  `anyone_can_modify` tinyint(1) NOT NULL DEFAULT '0',
  `email` varchar(100) DEFAULT NULL,
  `notification` varchar(250) DEFAULT NULL,
  `viewed_no` int(12) NOT NULL DEFAULT '0',
  `complexity_level` varchar(3) NOT NULL DEFAULT '-1',
  `auto_update_pwd_frequency` tinyint(2) NOT NULL DEFAULT '0',
  `auto_update_pwd_next_date` int(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restricted_inactif_idx` (`restricted_to`,`inactif`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8;

INSERT INTO teampass_items VALUES("1","OXid ZS","","07bda009291a046e0e196a8d95d64c09","bb73091dd75ece8945f88d18ea6995d9","0","http://oxid.demo","69","0","root","0","","0","","","11","54","0","");
INSERT INTO teampass_items VALUES("2","Oxid2e3","","0d656c1328280be9c9c2b542b33473e8","3df4bc4e647d1b0d6b845ce1277f1065","0","","5","0","","0","","0","","","7","56","0","");
INSERT INTO teampass_items VALUES("3","99999999999","","5d8969608e7677890c7c99c3fed85316","4bea23a5db4a5ba876f51b833f6aee3d","0","","7","0","","0","","0","","","3","51","0","");
INSERT INTO teampass_items VALUES("4","898989","","2ff97500953905f35c64029e36ae83f7","fad92e95a90f2892daa76b30a219c7cc","0","","6","0","","0","","0","","","3","54","0","");
INSERT INTO teampass_items VALUES("5","oxiddes","","f07e1dc57833a1366bc80e42f2644df3","96815d414abc008d99b4a139aab73ab9","0","","5","0","","0","","0","","","2","56","0","");
INSERT INTO teampass_items VALUES("6","oxiddes33","","2a223910897cdbbc3f93528d12e0143f","dfec2e23cb4b815a2379b7e10e09d22b","0","","5","0","","0","","0","","","1","71","0","");
INSERT INTO teampass_items VALUES("7","oxiddes33ewew","","24c134b19860843fd017dc4124fc4ea8","047f3c514c7462780e9a05667b536a14","0","http://admin.comxdesss","5","0","","0","","0","","","9","45","0","");
INSERT INTO teampass_items VALUES("8","oxideedsrr","","11d3355c2001d1772dbc43abfd97e6d5","82d4ccc500528a1040590d029f443518","0","http://zinit","5","0","magento","0","","0","","","4","49","0","");
INSERT INTO teampass_items VALUES("9","Test","","d2159b5fd817b04beab72605e90e1c27","2b5f0c274477a5bcd70a2c8c3153a30b","0","","25","0","","1","10000008;10000004;","0","","","2","64","0","");
INSERT INTO teampass_items VALUES("10","Admin Live","","994e123269af6313f483bef1619677af","9316056ae3cad97b63b1a3ff6c4ad9be","0","","28","0","sfdsf","1","","0","","","3","58","0","");
INSERT INTO teampass_items VALUES("11","testt","","d2d0bf2beda8f2aff5e86ee0ef6c01b7","c33dcd0d763532371d5ea122bc0ae45b","0","","32","0","","0","","0","","","3","71","0","");
INSERT INTO teampass_items VALUES("12","Plesk Live","","7e720ed79eb4ec329a7e21004198c090","aead9e05f62cacd786104781e6ea2d99","0","https://176-28-8-128.kundenadmin.hosteurope.de:8443/","70","0","admin","0","","0","","","4","51","0","");
INSERT INTO teampass_items VALUES("13","FTP Test","","8ab17325a58d44f86a1173baa1640630","2e42c5ae8f9e54ca9499ba2daa534ae7","0","ftp://sweet-beast.zinit1.com","70","0","sweet-beast","0","","0","","","4","89","0","");
INSERT INTO teampass_items VALUES("14","CDN Admin","","ac76e5df3898a249a8703cbcbe23e0f9","b5130d64753b1693e387eae871b66c9a","0","https://cp.maxcdn.com/account","70","0","zinittester@gmail.com","0","","0","","","4","85","0","");
INSERT INTO teampass_items VALUES("15","Database Test","for developers:<ul><li>host: db.zinit1.com</li><li>user: dev_sweet_beast</li><li>pass: q7rCqfuBQ36USJj4</li><li>database: dev_sweet_beast</li></ul>","442df7928eeac00ca114ab44b12de65f","83ddbc9a1c75cd6237ff971c8ee088f8","0","http://db.zinit1.com/phpmyadmin","70","0","sweet-beast2","0","","0","","","7","95","0","");
INSERT INTO teampass_items VALUES("16","FTP 1C","","b8e20cf88866f54d4402180bf72ae6eb","b2431f5357256683eb6f922b8aa59fc2","0","","70","0","client1c","0","","0","","","5","45","0","");
INSERT INTO teampass_items VALUES("17","ВКонтакте","","0a13475a0dbe709e264fd17019f894eb","8171b2bad51e60f0608e8595cc6327d4","0","http://vk.com/","70","0","sweet-beast@mail.ru","0","","0","","","4","46","0","");
INSERT INTO teampass_items VALUES("18","Instagram","","65294385d69618e993dbca6ff92580f85d879d04c091eb9f1b1ccd362a9ed3cb","46c6c57fc9100b84fef164c2cfd3d1ce","0","https://www.instagram.com/","70","0","sweet7beast","0","","0","","","3","79","0","");
INSERT INTO teampass_items VALUES("19","Одноклассники","","5cd68d4a02cdc7c03d31dc6daf9c4353","4d8e18d757605e0e36f39d2294ba4fc4","0","http://ok.ru/","70","0","SweetBeast","0","","0","","","3","46","0","");
INSERT INTO teampass_items VALUES("20","FTP storage for backups","","da1da6b869ce63aa1fd525c175af22e4","8e8af8b147182ee332112b8ce451e940","0","http://80.237.136.174/","70","0","ftp12543507-bak","0","","0","","","3","85","0","");
INSERT INTO teampass_items VALUES("21","FTP/SSH Live","Database:<ul><li>host: 176.28.8.128</li><li>user: sweetbea_zinit</li><li>pass: BlTkxO;OlB]1</li><li>database: sweetbea<em>zinit</em></li></ul>","c6f8089aad4033fddfa67757205844cb","e8cd7cf5af1dc64c72219e086fc3af36","0","http://176.28.8.128","70","0","root","0","","0","","","3","89","0","");
INSERT INTO teampass_items VALUES("23","E-mail sweetbeast7@gmail.com","","54eeb37c44b14a2832982cfce3c6dec5","837883973ec3b6b9627ca4bd6d42e93f","0","http://google.com","69","0","sweetbeast7@gmail.com","0","","0","","","6","34","0","");
INSERT INTO teampass_items VALUES("25","Facebook","","f301b008f466add2d57132423251468f","324bca29a60c03e6aa73669e9edbdb5b","0","https://www.facebook.com/","70","0","+380633364087","0","","0","","","3","70","0","");
INSERT INTO teampass_items VALUES("26","Admin Live/Test","учетка админа на тестовом и на продакшн","e6dac87cd9a13188cb4f5328e59ac6bd","74d874e5325d7f769681ec1121967bf7","0","http://sweet-beast.com/admin (sweet-beast.zinit1.com/admin)","46","0","zinit","0","","0","","","5","87","0","");
INSERT INTO teampass_items VALUES("27","test123","","8829cac4310619d9f1660034d76f629d","a75d3235889e3d7f60ca083e894bee42","0","","27","1","","0","","0","","","6","23","0","");
INSERT INTO teampass_items VALUES("28","Billing Live","","a43bc9253da429f167b82479e414b9d7","06ee7978114d4d0053d7244a9b2d825a","0","http://billing.hostpro.ua","70","0","max-zr@mail.ru","0","","0","","","2","87","0","");
INSERT INTO teampass_items VALUES("29","SSH Live","","8b976469c5aae315eeef3e0b8f3b0c15","0d69290b88986372dac7aa22829c2e8c","0","","32","0","","0","10000004;10000011;","0","","","2","50","0","");
INSERT INTO teampass_items VALUES("30","jkbhfdkjsbfas","","ba398eb362d2453aae1442da1ca57806","5af8ecfed5d68f060879458e37448827","0","","29","0","","0","10000004;10000011;","0","","","3","51","0","");
INSERT INTO teampass_items VALUES("31","Host Live","htaccess:<ul><li>user: sweet-portal</li><li>pass: 1E#A6Z3J-0RloGbCA</li></ul>MySQL:<ul><li>user: petsby</li><li>pass: Xj1zp&amp;64</li><li>base: petsby</li></ul>","e0bdbe910accd9bf5587156c06e56044","b5b0ef0f0fd53a09d50b4ed0658863e0","0","","71","0","petsby","0","","0","","","2","62","0","");
INSERT INTO teampass_items VALUES("32","Host test","MySQL:<ul><li>user:&nbsp;petsby</li><li>pass:&nbsp;yfBTob92v20mIjqP</li><li>base:&nbsp;petsby&nbsp;,&nbsp;petsby_dev</li><li>host:&nbsp;db.zinit1.com</li></ul>","30cfde5b04ab24614681c6f78d0ce203","e79548b66a62594943f9f686a4244d0a","0","http://petsby.zinit1.com","71","0","petsby","0","","0","","","2","87","0","");
INSERT INTO teampass_items VALUES("33","e-mail info@petsby.net","","2fae0354da4e6730a36a98bac13f0a21","638aa4773a619e6ec9fea21cda5a97f3","0","http://webmail.petsby.net","71","0","info@petsby.net","0","","0","","","1","47","0","");
INSERT INTO teampass_items VALUES("34","siteheart (Online help)","","1af87727c8bbff4405ea00a71fa96d851f0052fd922670e43927f7c0a0a9ee2c","3daf9ac8088491faabbb23fbc78ddfe9","0","https://www.siteheart.com/","71","0","info@petsby.net","0","","0","","","1","79","0","");
INSERT INTO teampass_items VALUES("35","Planning poker","","35c36786dea898f5ca7053dfac1b06e4","53cbb1fe288eb6d06014a809da00f776","0","https://app.planningpoker.com/login","71","0","piontkovsky.vladislav@gmail.com","0","","0","","","2","50","0","");
INSERT INTO teampass_items VALUES("36","Admin Test","","f151ac44205247542f2d3eccd983ba5f","4c4309d9aab8027400a4866cb53d946c","0","http://www.petsby.zinit1.com/admin/","71","0","zinit","0","","0","","","1","87","0","");
INSERT INTO teampass_items VALUES("37","Host Test","sweet-beast-ref<br />MySQL:<ul><li>user: sweet-beast-ref</li><li>base: sweet_beast_ref</li><li>pass: Zmi0DbqjKx2ymJbz</li><li>host: db.zinit1.com</li></ul>","2e52a062bc43669eb3227a5e1f209cbb","39a3cab566873e05036287c61ad6f5e4","0","http://sweet-beast-ref.zinit1.com/","70","0","sweet-beast-ref","0","","0","","","1","83","0","");
INSERT INTO teampass_items VALUES("38","FTP TEST","","0cf47d83b4f7170e32fbea897d3462ef","8accb854ef504dd3d62f92640a86f761","0","http://sweet-beast-store.zinit1.com","69","0","sweet-beast-store","0","","0","","","1","87","0","");
INSERT INTO teampass_items VALUES("39","FTP LIVE","","d48264c34f0fe63fc2cabf22f13005e3f65d3d08c74fb6607095d89ae15716b9","e17503b506edd330a5c02c16f3be1b78","0","http://176.28.8.128","69","0","sweetbeaststore","0","","0","","","2","100","0","");
INSERT INTO teampass_items VALUES("40","Database LIVE","DB name: sweet_beast_store","327a53874f775f4c77c0d74f1bf5dc0b8b243b6b5fbfc61f1b37980e0e09a58e","e432942d957dc3520f5d4e3693e802c3","0","","69","0","sweet-beast-stor","0","","0","","","1","100","0","");
INSERT INTO teampass_items VALUES("41","e-mail shop@sweet-beast.com","","580b3c5dbe04fc0e6cb36a713703ca6d6f58c3091407b57727379c1a180cd2af","a0db9eaeeb52a9f37acd27dcb57f9ae8","0","","69","0","shop@sweet-beast.com","0","","0","","","1","100","0","");
INSERT INTO teampass_items VALUES("42","Admin TEST","","a2a2e01d7025b8dbe93399ad54179ee2","67018a7ffb6788cb37929a1e5693d0ca","0","http://sweet-beast-store.zinit1.com/admin","69","0","admin@zinitsolutions.com","0","","0","","","1","79","0","");
INSERT INTO teampass_items VALUES("43","SSH Live (new)","","1cdce204c55377557eb5522da710fe83","d60739212b032fd55652ad339ccc6742","0","http://mail.newlogic.ua -p 2225","57","1","web-developer","0","","0","","","7","43","0","");
INSERT INTO teampass_items VALUES("44","testitem","","310e165e7dfd8486ae5f4251a1f57ae2","36f08010a43d3575b95541df504317f5","0","","69","0","","0","","0","","","3","59","0","");



DROP TABLE teampass_items_edition;

CREATE TABLE `teampass_items_edition` (
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `timestamp` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_items_edition VALUES("24","10000005","1469696445");
INSERT INTO teampass_items_edition VALUES("22","10000005","1469696452");
INSERT INTO teampass_items_edition VALUES("31","10000005","1469698136");
INSERT INTO teampass_items_edition VALUES("32","10000005","1469698258");
INSERT INTO teampass_items_edition VALUES("33","10000005","1469698466");
INSERT INTO teampass_items_edition VALUES("35","10000005","1469698716");
INSERT INTO teampass_items_edition VALUES("38","10000005","1469699133");
INSERT INTO teampass_items_edition VALUES("39","10000005","1469699180");
INSERT INTO teampass_items_edition VALUES("40","10000005","1469699288");
INSERT INTO teampass_items_edition VALUES("44","10000005","1469800575");



DROP TABLE teampass_kb;

CREATE TABLE `teampass_kb` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `category_id` int(12) NOT NULL,
  `label` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `author_id` int(12) NOT NULL,
  `anyone_can_modify` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_kb_categories;

CREATE TABLE `teampass_kb_categories` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_kb_items;

CREATE TABLE `teampass_kb_items` (
  `kb_id` tinyint(12) NOT NULL,
  `item_id` tinyint(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_languages;

CREATE TABLE `teampass_languages` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `label` varchar(50) NOT NULL,
  `code` varchar(10) NOT NULL,
  `flag` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;

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
INSERT INTO teampass_languages VALUES("12","chinese","Chinese","cn","cn.png");
INSERT INTO teampass_languages VALUES("13","swedish","Swedish","se","se.png");
INSERT INTO teampass_languages VALUES("14","dutch","Dutch","nl","nl.png");
INSERT INTO teampass_languages VALUES("15","catalan","Catalan","ct","ct.png");
INSERT INTO teampass_languages VALUES("16","vietnamese","Vietnamese","vi","vi.png");
INSERT INTO teampass_languages VALUES("17","estonia","Estonia","ee","ee.png");



DROP TABLE teampass_log_items;

CREATE TABLE `teampass_log_items` (
  `id_item` int(8) NOT NULL,
  `date` varchar(50) NOT NULL,
  `id_user` int(8) NOT NULL,
  `action` varchar(250) DEFAULT NULL,
  `raison` text,
  `raison_iv` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_log_items VALUES("1","1469195643","10000000","at_creation","","");
INSERT INTO teampass_log_items VALUES("1","1469195644","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469195646","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469195657","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469195665","10000000","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469195939","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469196272","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469196361","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469196364","10000000","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469196367","10000000","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("2","1469196473","10000000","at_creation","","");
INSERT INTO teampass_log_items VALUES("2","1469196473","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1469196475","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469197415","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1469197795","10000000","at_creation","","");
INSERT INTO teampass_log_items VALUES("3","1469197795","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("4","1469197849","10000000","at_creation","","");
INSERT INTO teampass_log_items VALUES("4","1469197850","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("4","1469197866","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("4","1469197891","10000000","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1469198023","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1469198094","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1469198120","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469198121","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469198162","10000001","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("1","1469198162","10000001","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("1","1469198162","10000001","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("1","1469198162","10000001","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("2","1469199032","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1469451313","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("5","1469451993","10000001","at_creation","","");
INSERT INTO teampass_log_items VALUES("6","1469452025","10000001","at_creation","","");
INSERT INTO teampass_log_items VALUES("7","1469452075","10000001","at_creation","","");
INSERT INTO teampass_log_items VALUES("7","1469452076","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469452085","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("7","1469452087","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("7","1469452163","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("7","1469452170","10000001","at_modification","at_url : http://admin.com => http://admin.comxdes","");
INSERT INTO teampass_log_items VALUES("7","1469452224","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("7","1469452227","10000001","at_modification","at_url : http://admin.comxdes => http://admin.comxdesss","");
INSERT INTO teampass_log_items VALUES("6","1469452731","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("5","1469452734","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469452750","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1469452757","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("5","1469452760","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1469452763","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1469452788","10000001","at_modification","at_label : Oxid2 => Oxid2e3","");
INSERT INTO teampass_log_items VALUES("2","1469452788","10000001","at_modification","at_pw :b0be1477ab33a2fbfc369cc3c56341b4","f10a3036e1a607e45e86e311a892d93b");
INSERT INTO teampass_log_items VALUES("8","1469452834","10000001","at_creation","","");
INSERT INTO teampass_log_items VALUES("8","1469452836","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("7","1469452886","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("7","1469452898","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("8","1469452910","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("7","1469452915","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("8","1469452923","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("8","1469452936","10000001","at_modification","at_url :  => http://zinit","");
INSERT INTO teampass_log_items VALUES("7","1469453984","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("8","1469453989","10000001","at_shown","","");
INSERT INTO teampass_log_items VALUES("4","1469626780","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("3","1469626780","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("1","1469626780","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("2","1469626780","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("5","1469626780","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("6","1469626780","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("7","1469626780","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("8","1469626780","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("9","1469630079","10000008","at_creation","","");
INSERT INTO teampass_log_items VALUES("9","1469630079","10000008","at_shown","","");
INSERT INTO teampass_log_items VALUES("9","1469630118","10000008","at_modification","at_restriction :  => o.marchenko;j.ilchenko","");
INSERT INTO teampass_log_items VALUES("9","1469630120","10000008","at_shown","","");
INSERT INTO teampass_log_items VALUES("9","1469630306","10000008","at_delete","","");
INSERT INTO teampass_log_items VALUES("10","1469630381","10000008","at_creation","","");
INSERT INTO teampass_log_items VALUES("10","1469630381","10000008","at_shown","","");
INSERT INTO teampass_log_items VALUES("10","1469630384","10000008","at_shown","","");
INSERT INTO teampass_log_items VALUES("10","1469630716","10000008","at_shown","","");
INSERT INTO teampass_log_items VALUES("10","1469630914","10000008","at_modification","at_moved : Sport -> Proteckt","");
INSERT INTO teampass_log_items VALUES("11","1469632485","10000010","at_creation","","");
INSERT INTO teampass_log_items VALUES("11","1469632486","10000010","at_shown","","");
INSERT INTO teampass_log_items VALUES("11","1469632487","10000010","at_shown","","");
INSERT INTO teampass_log_items VALUES("11","1469632997","10000010","at_shown","","");
INSERT INTO teampass_log_items VALUES("12","1469694784","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("12","1469694784","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("13","1469694871","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("13","1469694871","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("13","1469694876","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("12","1469694880","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("12","1469694896","10000005","at_modification","at_url :  => https://176-28-8-128.kundenadmin.hosteurope.de:8443/","");
INSERT INTO teampass_log_items VALUES("14","1469694971","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("14","1469694972","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("15","1469695139","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("15","1469695140","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("14","1469695143","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("14","1469695148","10000005","at_modification","at_label :  CDN Admin => CDN Admin","");
INSERT INTO teampass_log_items VALUES("15","1469695172","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("15","1469695314","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("15","1469695320","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("15","1469695323","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("16","1469695556","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("16","1469695557","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("17","1469695717","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("17","1469695717","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("17","1469695725","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("17","1469695759","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("17","1469695768","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("17","1469695776","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("16","1469695779","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("16","1469695784","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("15","1469695793","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("15","1469695808","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("18","1469695869","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("18","1469695870","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("19","1469695950","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("19","1469695950","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("20","1469696077","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("20","1469696077","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("21","1469696140","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("21","1469696140","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("21","1469696152","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("16","1469696165","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("21","1469696170","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("18","1469696176","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("12","1469696178","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("19","1469696191","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("13","1469696202","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("20","1469696259","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("16","1469696260","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("15","1469696265","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("14","1469696266","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("14","1469696272","10000009","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696274","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("14","1469696287","10000009","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("22","1469696311","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("10","1469696323","10000009","at_delete","","");
INSERT INTO teampass_log_items VALUES("23","1469696324","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("24","1469696343","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("24","1469696361","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("23","1469696364","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("22","1469696365","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("24","1469696371","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("24","1469696420","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("24","1469696448","10000005","at_delete","","");
INSERT INTO teampass_log_items VALUES("22","1469696450","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("22","1469696453","10000005","at_delete","","");
INSERT INTO teampass_log_items VALUES("23","1469696471","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("25","1469696606","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("25","1469696606","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("26","1469696916","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("26","1469696916","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("26","1469697060","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469697124","10000011","at_creation","","");
INSERT INTO teampass_log_items VALUES("27","1469697124","10000011","at_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469697126","10000011","at_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469697128","10000011","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469697135","10000011","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("27","1469697135","10000011","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("27","1469697136","10000011","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("27","1469697136","10000011","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("26","1469697210","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("14","1469697306","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("15","1469697310","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("23","1469697340","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("23","1469697350","10000005","at_modification","at_pw :67022f02130be987aa075c1eae751979","6803d75fe71a3638706e8dcbc9ab6587");
INSERT INTO teampass_log_items VALUES("23","1469697352","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("23","1469697356","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("23","1469697358","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("25","1469697362","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("16","1469697367","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("16","1469697382","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("16","1469697382","10000005","at_modification","at_pw :6e59d08ac2afbfe8b4a19472fde1a073","a1450c09ec98ad8d398ff502d5f71136");
INSERT INTO teampass_log_items VALUES("20","1469697383","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("13","1469697385","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("21","1469697388","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("18","1469697392","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("12","1469697395","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("17","1469697397","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("17","1469697407","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("17","1469697407","10000005","at_modification","at_pw :405352c9b2a80314635e7d47d8c82f6c","65457d4072a81d732e11a5bb056aa9a8");
INSERT INTO teampass_log_items VALUES("19","1469697408","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("19","1469697421","10000005","at_modification","at_description","");
INSERT INTO teampass_log_items VALUES("19","1469697421","10000005","at_modification","at_pw :0e0ad67d1c3a6f0336c352b85908cce0","015e470ed6c334512623d35fe25f8b8a");
INSERT INTO teampass_log_items VALUES("26","1469697426","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("28","1469697569","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("28","1469697570","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("28","1469697599","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469697649","10000011","at_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469697670","10000011","at_shown","","");
INSERT INTO teampass_log_items VALUES("25","1469697680","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1469697837","10000004","at_creation","","");
INSERT INTO teampass_log_items VALUES("29","1469697837","10000004","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1469697839","10000004","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1469697873","10000004","at_modification","at_restriction :  => j.ilchenko;d.tereshchuk","");
INSERT INTO teampass_log_items VALUES("30","1469698038","10000004","at_creation","","");
INSERT INTO teampass_log_items VALUES("30","1469698039","10000004","at_shown","","");
INSERT INTO teampass_log_items VALUES("30","1469698040","10000004","at_shown","","");
INSERT INTO teampass_log_items VALUES("30","1469698055","10000004","at_modification","at_restriction :  => j.ilchenko;d.tereshchuk","");
INSERT INTO teampass_log_items VALUES("30","1469698097","10000004","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1469698111","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("31","1469698112","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("32","1469698188","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("32","1469698188","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("33","1469698418","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("33","1469698419","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("34","1469698512","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("34","1469698513","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469698627","10000011","at_shown","","");
INSERT INTO teampass_log_items VALUES("35","1469698697","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("35","1469698698","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("36","1469698755","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("36","1469698756","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("37","1469698900","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("37","1469698900","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469698991","10000011","at_shown","","");
INSERT INTO teampass_log_items VALUES("38","1469699117","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("38","1469699118","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("39","1469699168","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("39","1469699169","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("40","1469699261","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("40","1469699261","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("41","1469699363","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("41","1469699364","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("42","1469699420","10000005","at_creation","","");
INSERT INTO teampass_log_items VALUES("42","1469699420","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("27","1469699674","10000011","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("43","1469708748","10000016","at_creation","","");
INSERT INTO teampass_log_items VALUES("43","1469708748","10000016","at_shown","","");
INSERT INTO teampass_log_items VALUES("43","1469708752","10000016","at_shown","","");
INSERT INTO teampass_log_items VALUES("43","1469708755","10000016","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("43","1469708760","10000016","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("43","1469708761","10000016","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("43","1469708805","10000016","at_shown","","");
INSERT INTO teampass_log_items VALUES("43","1469708830","10000016","at_modification","at_pw :f7937d7a9125402b0574595679c8feaf","de939c5e4ad2d39f4f4f4b240618954e");
INSERT INTO teampass_log_items VALUES("43","1469708839","10000016","at_shown","","");
INSERT INTO teampass_log_items VALUES("43","1469708872","10000016","at_shown","","");
INSERT INTO teampass_log_items VALUES("43","1469709028","10000016","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("43","1469709044","10000016","at_shown","","");
INSERT INTO teampass_log_items VALUES("43","1469785396","10000016","at_shown","","");
INSERT INTO teampass_log_items VALUES("26","1469790304","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("30","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("11","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("29","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("27","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("43","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("42","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("38","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("39","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("40","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("41","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("35","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("36","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("31","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("32","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("33","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("34","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("26","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("12","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("13","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("14","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("15","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("16","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("17","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("18","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("19","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("20","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("21","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("22","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("23","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("24","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("25","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("28","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("37","1469790421","1","at_delete","","");
INSERT INTO teampass_log_items VALUES("44","1469799091","10000009","at_creation","","");
INSERT INTO teampass_log_items VALUES("44","1469799091","10000009","at_shown","","");
INSERT INTO teampass_log_items VALUES("44","1469799407","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("22","1469800145","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("23","1469800145","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("24","1469800145","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("16","1469800181","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("17","1469800181","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("18","1469800181","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("19","1469800181","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("20","1469800181","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("21","1469800181","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("25","1469800181","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("26","1469800181","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("0","1469800260","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("1","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("2","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("3","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("4","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("5","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("6","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("7","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("8","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("9","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("10","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("11","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("12","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("13","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("14","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("15","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("27","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("28","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("29","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("30","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("31","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("32","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("33","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("34","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("35","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("36","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("37","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("38","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("39","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("40","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("41","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("42","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("43","1469800284","1","at_restored","","");
INSERT INTO teampass_log_items VALUES("9","1469800333","10000005","at_delete","","");
INSERT INTO teampass_log_items VALUES("10","1469800333","10000005","at_delete","","");
INSERT INTO teampass_log_items VALUES("23","1469800408","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("23","1469800409","10000005","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("39","1469800567","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1469800568","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("44","1469800571","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("32","1469800647","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("32","1469800653","10000005","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("35","1469800885","10000005","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1469800905","10000005","at_shown","","");



DROP TABLE teampass_log_system;

CREATE TABLE `teampass_log_system` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `date` varchar(30) NOT NULL,
  `label` text NOT NULL,
  `qui` varchar(30) NOT NULL,
  `field_1` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=229 DEFAULT CHARSET=utf8;

INSERT INTO teampass_log_system VALUES("1","failed_auth","1467671549","user_password_not_correct","46.211.253.184","");
INSERT INTO teampass_log_system VALUES("2","failed_auth","1467671563","user_password_not_correct","46.211.253.184","");
INSERT INTO teampass_log_system VALUES("3","user_mngt","1469195069","at_user_added","1","10000000");
INSERT INTO teampass_log_system VALUES("4","failed_auth","1469195143","user_not_exists","172.17.0.1","");
INSERT INTO teampass_log_system VALUES("5","failed_auth","1469195164","user_not_exists","172.17.0.1","");
INSERT INTO teampass_log_system VALUES("6","user_mngt","1469195178","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("7","failed_auth","1469195182","user_not_exists","172.17.0.1","");
INSERT INTO teampass_log_system VALUES("8","failed_auth","1469195221","user_not_exists","172.17.0.1","");
INSERT INTO teampass_log_system VALUES("9","failed_auth","1469195329","user_not_exists","172.17.0.1","");
INSERT INTO teampass_log_system VALUES("10","user_connection","1469195469","connection","1","");
INSERT INTO teampass_log_system VALUES("11","user_connection","1469195530","connection","10000000","");
INSERT INTO teampass_log_system VALUES("12","user_connection","1469195742","connection","1","");
INSERT INTO teampass_log_system VALUES("13","user_connection","1469195784","connection","1","");
INSERT INTO teampass_log_system VALUES("14","user_connection","1469195797","connection","1","");
INSERT INTO teampass_log_system VALUES("15","user_mngt","1469197679","at_user_added","1","10000001");
INSERT INTO teampass_log_system VALUES("16","user_connection","1469197976","connection","10000001","");
INSERT INTO teampass_log_system VALUES("17","user_mngt","1469197982","at_user_initial_pwd_changed","10000001","10000001");
INSERT INTO teampass_log_system VALUES("18","user_connection","1469198560","connection","1","");
INSERT INTO teampass_log_system VALUES("19","user_connection","1469199459","connection","10000001","");
INSERT INTO teampass_log_system VALUES("20","user_connection","1469201211","connection","1","");
INSERT INTO teampass_log_system VALUES("21","user_connection","1469201426","connection","1","");
INSERT INTO teampass_log_system VALUES("22","user_connection","1469435859","connection","1","");
INSERT INTO teampass_log_system VALUES("23","failed_auth","1469435885","user_not_exists","172.27.0.1","");
INSERT INTO teampass_log_system VALUES("24","failed_auth","1469435891","user_not_exists","172.27.0.1","");
INSERT INTO teampass_log_system VALUES("25","user_connection","1469435899","connection","1","");
INSERT INTO teampass_log_system VALUES("26","failed_auth","1469435929","user_not_exists","172.27.0.1","");
INSERT INTO teampass_log_system VALUES("27","user_connection","1469435935","connection","1","");
INSERT INTO teampass_log_system VALUES("28","user_connection","1469435951","connection","10000001","");
INSERT INTO teampass_log_system VALUES("29","error","1469438257","Query: SELECT id, label, description, tags, id_tree, perso, restricted_to, login, folder, author, renewal_period, timestamp\n    FROM teampass_cache\n    WHERE id_tree IN (\'3\',\'5\',\'7\') AND (id LIKE \'%5%\' OR label LIKE \'%5%\' OR description LIKE \'%5%\' OR tags LIKE \'%5%\' OR id_tree LIKE \'%5%\' OR folder LIKE \'%5%\' OR login LIKE \'%5%\' OR url LIKE \'%5%\' ) \n    ORDER BY  label asc\n    LIMIT 0, 10<br />Error: Unknown column \'url\' in \'where clause\'<br />@ ","10000001","");
INSERT INTO teampass_log_system VALUES("30","failed_auth","1469438554","user_not_exists","172.27.0.1","");
INSERT INTO teampass_log_system VALUES("31","failed_auth","1469438560","user_not_exists","172.27.0.1","");
INSERT INTO teampass_log_system VALUES("32","user_connection","1469438621","connection","1","");
INSERT INTO teampass_log_system VALUES("33","error","1469440608","Query: urel<br />Error: You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \'urel\' at line 1<br />@ ","10000001","");
INSERT INTO teampass_log_system VALUES("34","user_connection","1469451281","disconnection","1","");
INSERT INTO teampass_log_system VALUES("35","user_connection","1469451305","connection","10000001","");
INSERT INTO teampass_log_system VALUES("36","user_connection","1469451761","connection","1","");
INSERT INTO teampass_log_system VALUES("37","failed_auth","1469451836","user_not_exists","172.27.0.1","");
INSERT INTO teampass_log_system VALUES("38","failed_auth","1469451856","user_not_exists","172.27.0.1","");
INSERT INTO teampass_log_system VALUES("39","failed_auth","1469451857","user_not_exists","172.27.0.1","");
INSERT INTO teampass_log_system VALUES("40","user_connection","1469451861","disconnection","10000001","");
INSERT INTO teampass_log_system VALUES("41","user_connection","1469451868","connection","10000001","");
INSERT INTO teampass_log_system VALUES("42","user_connection","1469451929","connection","1","");
INSERT INTO teampass_log_system VALUES("43","error","1469451993","Query: INSERT INTO `teampass_cache` (`id`,`label`,`description`,`tags`,`url`,`id_tree`,`perso`,`restricted_to`,`login`,`folder`,`author`,`timestamp`) VALUES (\'5\', \'oxiddes\', \'\', \'\', NULL, \'5\', \'0\', \'\', \'\', \'Test » Oxid\', \'10000001\', \'1469451993\')<br />Error: Column \'url\' cannot be null<br />@ ","10000001","");
INSERT INTO teampass_log_system VALUES("44","error","1469452025","Query: INSERT INTO `teampass_cache` (`id`,`label`,`description`,`tags`,`url`,`id_tree`,`perso`,`restricted_to`,`login`,`folder`,`author`,`timestamp`) VALUES (\'6\', \'oxiddes33\', \'\', \'\', NULL, \'5\', \'0\', \'\', \'\', \'Test » Oxid\', \'10000001\', \'1469452025\')<br />Error: Column \'url\' cannot be null<br />@ ","10000001","");
INSERT INTO teampass_log_system VALUES("45","user_connection","1469527880","connection","1","");
INSERT INTO teampass_log_system VALUES("46","user_mngt","1469528117","at_user_added","1","10000002");
INSERT INTO teampass_log_system VALUES("47","user_connection","1469542032","connection","1","");
INSERT INTO teampass_log_system VALUES("48","user_connection","1469542061","connection","1","");
INSERT INTO teampass_log_system VALUES("49","user_mngt","1469543148","at_user_added","1","10000003");
INSERT INTO teampass_log_system VALUES("50","user_mngt","1469543206","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("51","user_mngt","1469543329","at_user_added","1","10000004");
INSERT INTO teampass_log_system VALUES("52","user_mngt","1469543353","at_user_locked","1","10000000");
INSERT INTO teampass_log_system VALUES("53","user_mngt","1469543397","at_user_added","1","10000005");
INSERT INTO teampass_log_system VALUES("54","user_mngt","1469543437","at_user_added","1","10000006");
INSERT INTO teampass_log_system VALUES("55","user_connection","1469543442","connection","10000004","");
INSERT INTO teampass_log_system VALUES("56","user_mngt","1469543460","at_user_initial_pwd_changed","10000004","10000004");
INSERT INTO teampass_log_system VALUES("57","user_mngt","1469543487","at_user_added","1","10000007");
INSERT INTO teampass_log_system VALUES("58","user_mngt","1469543558","at_user_added","1","10000008");
INSERT INTO teampass_log_system VALUES("59","user_mngt","1469543592","at_user_added","1","10000009");
INSERT INTO teampass_log_system VALUES("60","user_mngt","1469543682","at_user_added","1","10000010");
INSERT INTO teampass_log_system VALUES("61","admin_action","1469543900","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("62","user_mngt","1469543984","at_user_initial_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("63","admin_action","1469543990","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("64","user_mngt","1469544085","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("65","user_connection","1469545758","disconnection","1","");
INSERT INTO teampass_log_system VALUES("66","failed_auth","1469626717","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("67","user_connection","1469626743","connection","1","");
INSERT INTO teampass_log_system VALUES("68","user_connection","1469627425","connection","10000010","");
INSERT INTO teampass_log_system VALUES("69","user_mngt","1469627448","at_user_initial_pwd_changed","10000010","10000010");
INSERT INTO teampass_log_system VALUES("70","failed_auth","1469627541","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("71","failed_auth","1469627558","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("72","failed_auth","1469627578","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("73","failed_auth","1469627593","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("74","failed_auth","1469627605","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("75","failed_auth","1469627639","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("76","failed_auth","1469627650","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("77","failed_auth","1469627818","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("78","failed_auth","1469627901","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("79","failed_auth","1469627925","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("80","failed_auth","1469628014","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("81","failed_auth","1469628366","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("82","user_locked","1469628366","connection","10000010","");
INSERT INTO teampass_log_system VALUES("83","failed_auth","1469628478","user_not_exists","194.44.216.55","");
INSERT INTO teampass_log_system VALUES("84","user_connection","1469628977","connection","1","");
INSERT INTO teampass_log_system VALUES("85","user_mngt","1469629023","at_user_unlocked","1","10000010");
INSERT INTO teampass_log_system VALUES("86","user_mngt","1469629040","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("87","user_connection","1469629093","connection","10000010","");
INSERT INTO teampass_log_system VALUES("88","user_connection","1469629273","connection","10000008","");
INSERT INTO teampass_log_system VALUES("89","user_connection","1469629315","connection","1","");
INSERT INTO teampass_log_system VALUES("90","error","1469629342","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000010","");
INSERT INTO teampass_log_system VALUES("91","error","1469629357","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000010","");
INSERT INTO teampass_log_system VALUES("92","user_mngt","1469629383","at_user_initial_pwd_changed","10000008","10000008");
INSERT INTO teampass_log_system VALUES("93","error","1469629514","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000010","");
INSERT INTO teampass_log_system VALUES("94","failed_auth","1469629642","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("95","failed_auth","1469629669","user_password_not_correct","94.158.95.35","");
INSERT INTO teampass_log_system VALUES("96","user_connection","1469629683","connection","10000010","");
INSERT INTO teampass_log_system VALUES("97","user_connection","1469630412","connection","1","");
INSERT INTO teampass_log_system VALUES("98","user_mngt","1469630473","at_user_added","1","10000011");
INSERT INTO teampass_log_system VALUES("99","user_connection","1469630537","connection","10000011","");
INSERT INTO teampass_log_system VALUES("100","user_mngt","1469630614","at_user_initial_pwd_changed","10000011","10000011");
INSERT INTO teampass_log_system VALUES("101","error","1469630656","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("102","error","1469630667","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("103","error","1469631183","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("104","error","1469631190","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("105","error","1469631194","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("106","error","1469631224","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("107","error","1469631269","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("108","error","1469631276","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("109","failed_auth","1469631342","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("110","failed_auth","1469631358","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("111","user_connection","1469631370","connection","1","");
INSERT INTO teampass_log_system VALUES("112","user_mngt","1469631391","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("113","failed_auth","1469631425","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("114","failed_auth","1469631436","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("115","user_mngt","1469631456","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("116","failed_auth","1469631460","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("117","user_connection","1469631493","connection","10000011","");
INSERT INTO teampass_log_system VALUES("118","error","1469631581","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("119","error","1469631589","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("120","user_connection","1469631596","connection","1","");
INSERT INTO teampass_log_system VALUES("121","error","1469631598","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("122","user_connection","1469631696","connection","1","");
INSERT INTO teampass_log_system VALUES("123","user_mngt","1469631716","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("124","failed_auth","1469631742","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("125","failed_auth","1469631747","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("126","error","1469631964","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000010","");
INSERT INTO teampass_log_system VALUES("127","error","1469631981","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000010","");
INSERT INTO teampass_log_system VALUES("128","user_connection","1469632158","connection","10000011","");
INSERT INTO teampass_log_system VALUES("129","error","1469632218","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("130","user_connection","1469632221","connection","1","");
INSERT INTO teampass_log_system VALUES("131","error","1469632222","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (Array)\n                ORDER BY complexity DESC<br />Error: Unknown column \'Array\' in \'where clause\'<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("132","admin_action","1469632798","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("133","user_connection","1469686564","connection","10000005","");
INSERT INTO teampass_log_system VALUES("134","user_mngt","1469686635","at_user_initial_pwd_changed","10000005","10000005");
INSERT INTO teampass_log_system VALUES("135","failed_auth","1469686739","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("136","user_connection","1469686752","connection","1","");
INSERT INTO teampass_log_system VALUES("137","user_connection","1469687078","connection","10000005","");
INSERT INTO teampass_log_system VALUES("138","user_connection","1469687102","connection","10000005","");
INSERT INTO teampass_log_system VALUES("139","user_connection","1469690449","disconnection","1","");
INSERT INTO teampass_log_system VALUES("140","user_connection","1469691127","connection","1","");
INSERT INTO teampass_log_system VALUES("141","user_connection","1469691263","connection","10000005","");
INSERT INTO teampass_log_system VALUES("142","user_connection","1469691479","connection","10000005","");
INSERT INTO teampass_log_system VALUES("143","user_connection","1469694345","connection","10000009","");
INSERT INTO teampass_log_system VALUES("144","user_mngt","1469694378","at_user_initial_pwd_changed","10000009","10000009");
INSERT INTO teampass_log_system VALUES("145","user_connection","1469694825","disconnection","1","");
INSERT INTO teampass_log_system VALUES("146","failed_auth","1469695201","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("147","user_connection","1469695215","connection","10000005","");
INSERT INTO teampass_log_system VALUES("148","user_connection","1469695407","connection","10000011","");
INSERT INTO teampass_log_system VALUES("149","user_mngt","1469695435","at_user_pwd_changed","10000011","10000011");
INSERT INTO teampass_log_system VALUES("150","failed_auth","1469695458","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("151","user_connection","1469695462","connection","10000011","");
INSERT INTO teampass_log_system VALUES("152","user_connection","1469695899","connection","1","");
INSERT INTO teampass_log_system VALUES("153","user_mngt","1469696031","at_user_added","1","10000012");
INSERT INTO teampass_log_system VALUES("154","user_connection","1469696145","connection","10000009","");
INSERT INTO teampass_log_system VALUES("155","user_connection","1469696445","connection","1","");
INSERT INTO teampass_log_system VALUES("156","failed_auth","1469697002","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("157","user_connection","1469697040","connection","10000011","");
INSERT INTO teampass_log_system VALUES("158","user_connection","1469697135","connection","1","");
INSERT INTO teampass_log_system VALUES("159","user_connection","1469697376","connection","1","");
INSERT INTO teampass_log_system VALUES("160","user_connection","1469697405","connection","1","");
INSERT INTO teampass_log_system VALUES("161","user_connection","1469697594","connection","1","");
INSERT INTO teampass_log_system VALUES("162","failed_auth","1469697636","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("163","user_connection","1469697644","connection","10000011","");
INSERT INTO teampass_log_system VALUES("164","user_connection","1469697667","connection","10000011","");
INSERT INTO teampass_log_system VALUES("165","user_connection","1469697811","connection","10000004","");
INSERT INTO teampass_log_system VALUES("166","user_connection","1469698987","connection","10000011","");
INSERT INTO teampass_log_system VALUES("167","user_connection","1469705184","connection","1","");
INSERT INTO teampass_log_system VALUES("168","user_mngt","1469705256","at_user_added","1","10000013");
INSERT INTO teampass_log_system VALUES("169","user_connection","1469705273","connection","10000013","");
INSERT INTO teampass_log_system VALUES("170","user_mngt","1469705280","at_user_initial_pwd_changed","10000013","10000013");
INSERT INTO teampass_log_system VALUES("171","user_connection","1469705359","connection","1","");
INSERT INTO teampass_log_system VALUES("172","user_mngt","1469705710","at_user_locked","1","10000013");
INSERT INTO teampass_log_system VALUES("173","user_mngt","1469705732","at_user_deleted","1","10000013");
INSERT INTO teampass_log_system VALUES("174","user_mngt","1469705832","at_user_added","1","10000014");
INSERT INTO teampass_log_system VALUES("175","user_mngt","1469706052","at_user_locked","1","10000014");
INSERT INTO teampass_log_system VALUES("176","user_mngt","1469706062","at_user_deleted","1","10000014");
INSERT INTO teampass_log_system VALUES("177","user_mngt","1469706102","at_user_added","1","10000015");
INSERT INTO teampass_log_system VALUES("178","user_mngt","1469707805","at_user_locked","1","10000015");
INSERT INTO teampass_log_system VALUES("179","user_mngt","1469707810","at_user_deleted","1","10000015");
INSERT INTO teampass_log_system VALUES("180","user_mngt","1469707879","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("181","user_connection","1469707929","connection","10000011","");
INSERT INTO teampass_log_system VALUES("182","user_mngt","1469708069","at_user_added","1","10000016");
INSERT INTO teampass_log_system VALUES("183","error","1469708075","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (3;5)\n                ORDER BY complexity DESC<br />Error: You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \';5)\n                ORDER BY complexity DESC\' at line 3<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("184","error","1469708143","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (3;5)\n                ORDER BY complexity DESC<br />Error: You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \';5)\n                ORDER BY complexity DESC\' at line 3<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("185","user_connection","1469708148","connection","10000016","");
INSERT INTO teampass_log_system VALUES("186","error","1469708206","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (3;5)\n                ORDER BY complexity DESC<br />Error: You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \';5)\n                ORDER BY complexity DESC\' at line 3<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("187","user_mngt","1469708216","at_user_initial_pwd_changed","10000016","10000016");
INSERT INTO teampass_log_system VALUES("188","user_connection","1469708249","connection","10000016","");
INSERT INTO teampass_log_system VALUES("189","failed_auth","1469708284","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("190","user_connection","1469708291","connection","10000011","");
INSERT INTO teampass_log_system VALUES("191","user_connection","1469708310","connection","10000016","");
INSERT INTO teampass_log_system VALUES("192","error","1469708331","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (3;5)\n                ORDER BY complexity DESC<br />Error: You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \';5)\n                ORDER BY complexity DESC\' at line 3<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("193","error","1469708367","Query: SELECT complexity\n                FROM teampass_roles_title\n                WHERE id IN (3;5)\n                ORDER BY complexity DESC<br />Error: You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near \';5)\n                ORDER BY complexity DESC\' at line 3<br />@ ","10000011","");
INSERT INTO teampass_log_system VALUES("194","user_connection","1469772571","connection","10000011","");
INSERT INTO teampass_log_system VALUES("195","user_mngt","1469772600","at_user_pwd_changed","10000011","10000011");
INSERT INTO teampass_log_system VALUES("196","user_connection","1469772622","connection","10000011","");
INSERT INTO teampass_log_system VALUES("197","user_connection","1469772721","connection","10000011","");
INSERT INTO teampass_log_system VALUES("198","user_connection","1469785353","connection","10000016","");
INSERT INTO teampass_log_system VALUES("199","user_connection","1469790290","connection","10000005","");
INSERT INTO teampass_log_system VALUES("200","user_connection","1469790335","connection","1","");
INSERT INTO teampass_log_system VALUES("201","user_connection","1469790437","connection","1","");
INSERT INTO teampass_log_system VALUES("202","user_connection","1469790630","connection","1","");
INSERT INTO teampass_log_system VALUES("203","user_connection","1469796766","connection","1","");
INSERT INTO teampass_log_system VALUES("204","user_connection","1469797928","connection","1","");
INSERT INTO teampass_log_system VALUES("205","failed_auth","1469798125","user_password_not_correct","5.9.88.141","");
INSERT INTO teampass_log_system VALUES("206","failed_auth","1469798136","user_password_not_correct","5.9.88.141","");
INSERT INTO teampass_log_system VALUES("207","user_connection","1469798158","connection","1","");
INSERT INTO teampass_log_system VALUES("208","user_connection","1469798167","connection","1","");
INSERT INTO teampass_log_system VALUES("209","failed_auth","1469798429","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("210","failed_auth","1469798438","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("211","user_mngt","1469798460","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("212","failed_auth","1469798469","user_password_not_correct","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("213","user_mngt","1469798511","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("214","user_connection","1469798516","connection","10000009","");
INSERT INTO teampass_log_system VALUES("215","admin_action","1469799239","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("216","failed_auth","1469799314","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("217","failed_auth","1469799322","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("218","failed_auth","1469799373","user_not_exists","109.251.188.72","");
INSERT INTO teampass_log_system VALUES("219","error","1469799719","Query: SELECT email,login FROM teampass_users WHERE id= undefined<br />Error: Unknown column \'undefined\' in \'where clause\'<br />@ ","10000009","");
INSERT INTO teampass_log_system VALUES("220","user_mngt","1469799902","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("221","user_connection","1469799913","connection","10000005","");
INSERT INTO teampass_log_system VALUES("222","admin_action","1469800537","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("223","admin_action","1469800634","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("224","admin_action","1469800719","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("225","admin_action","1469800878","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("226","user_connection","1469801492","connection","1","");
INSERT INTO teampass_log_system VALUES("227","user_mngt","1469801511","at_user_initial_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("228","user_connection","1469801622","connection","10000005","");



DROP TABLE teampass_misc;

CREATE TABLE `teampass_misc` (
  `type` varchar(50) NOT NULL,
  `intitule` varchar(100) NOT NULL,
  `valeur` varchar(100) NOT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=220 DEFAULT CHARSET=utf8;

INSERT INTO teampass_misc VALUES("admin","max_latest_items","10","1");
INSERT INTO teampass_misc VALUES("admin","enable_favourites","1","2");
INSERT INTO teampass_misc VALUES("admin","show_last_items","1","3");
INSERT INTO teampass_misc VALUES("admin","enable_pf_feature","1","4");
INSERT INTO teampass_misc VALUES("admin","log_connections","1","5");
INSERT INTO teampass_misc VALUES("admin","log_accessed","1","6");
INSERT INTO teampass_misc VALUES("admin","time_format","H:i:s","7");
INSERT INTO teampass_misc VALUES("admin","date_format","d/m/Y","8");
INSERT INTO teampass_misc VALUES("admin","duplicate_folder","1","9");
INSERT INTO teampass_misc VALUES("admin","item_duplicate_in_same_folder","0","10");
INSERT INTO teampass_misc VALUES("admin","duplicate_item","1","11");
INSERT INTO teampass_misc VALUES("admin","number_of_used_pw","3","12");
INSERT INTO teampass_misc VALUES("admin","manager_edit","1","13");
INSERT INTO teampass_misc VALUES("admin","cpassman_dir","/var/www/html","14");
INSERT INTO teampass_misc VALUES("admin","cpassman_url","http://localhost:8000","15");
INSERT INTO teampass_misc VALUES("admin","favicon","http://localhost:8000/favico.ico","16");
INSERT INTO teampass_misc VALUES("admin","path_to_upload_folder","/var/www/html/upload","17");
INSERT INTO teampass_misc VALUES("admin","url_to_upload_folder","http://localhost:8000/upload","18");
INSERT INTO teampass_misc VALUES("admin","path_to_files_folder","/var/www/html/files","19");
INSERT INTO teampass_misc VALUES("admin","url_to_files_folder","http://localhost:8000/files","20");
INSERT INTO teampass_misc VALUES("admin","activate_expiration","0","21");
INSERT INTO teampass_misc VALUES("admin","pw_life_duration","30","22");
INSERT INTO teampass_misc VALUES("admin","maintenance_mode","0","23");
INSERT INTO teampass_misc VALUES("admin","enable_sts","0","24");
INSERT INTO teampass_misc VALUES("admin","encryptClientServer","1","25");
INSERT INTO teampass_misc VALUES("admin","cpassman_version","2.1.26","26");
INSERT INTO teampass_misc VALUES("admin","ldap_mode","0","27");
INSERT INTO teampass_misc VALUES("admin","ldap_type","0","28");
INSERT INTO teampass_misc VALUES("admin","ldap_suffix","0","29");
INSERT INTO teampass_misc VALUES("admin","ldap_domain_dn","0","30");
INSERT INTO teampass_misc VALUES("admin","ldap_domain_controler","0","31");
INSERT INTO teampass_misc VALUES("admin","ldap_user_attribute","0","32");
INSERT INTO teampass_misc VALUES("admin","ldap_ssl","0","33");
INSERT INTO teampass_misc VALUES("admin","ldap_tls","0","34");
INSERT INTO teampass_misc VALUES("admin","ldap_elusers","0","35");
INSERT INTO teampass_misc VALUES("admin","richtext","0","36");
INSERT INTO teampass_misc VALUES("admin","allow_print","0","37");
INSERT INTO teampass_misc VALUES("admin","roles_allowed_to_print","0","38");
INSERT INTO teampass_misc VALUES("admin","show_description","1","39");
INSERT INTO teampass_misc VALUES("admin","anyone_can_modify","0","40");
INSERT INTO teampass_misc VALUES("admin","anyone_can_modify_bydefault","0","41");
INSERT INTO teampass_misc VALUES("admin","nb_bad_authentication","10","42");
INSERT INTO teampass_misc VALUES("admin","utf8_enabled","1","43");
INSERT INTO teampass_misc VALUES("admin","restricted_to","1","44");
INSERT INTO teampass_misc VALUES("admin","restricted_to_roles","1","45");
INSERT INTO teampass_misc VALUES("admin","enable_send_email_on_user_login","0","46");
INSERT INTO teampass_misc VALUES("admin","enable_user_can_create_folders","1","47");
INSERT INTO teampass_misc VALUES("admin","insert_manual_entry_item_history","0","48");
INSERT INTO teampass_misc VALUES("admin","enable_kb","0","49");
INSERT INTO teampass_misc VALUES("admin","enable_email_notification_on_item_shown","0","50");
INSERT INTO teampass_misc VALUES("admin","enable_email_notification_on_user_pw_change","0","51");
INSERT INTO teampass_misc VALUES("admin","custom_logo","https://teampass.zinit1.com/logo_white.png","52");
INSERT INTO teampass_misc VALUES("admin","custom_login_text","","53");
INSERT INTO teampass_misc VALUES("admin","default_language","english","54");
INSERT INTO teampass_misc VALUES("admin","send_stats","false","55");
INSERT INTO teampass_misc VALUES("admin","get_tp_info","0","56");
INSERT INTO teampass_misc VALUES("admin","send_mail_on_user_login","0","57");
INSERT INTO teampass_misc VALUES("cron","sending_emails","0","58");
INSERT INTO teampass_misc VALUES("admin","nb_items_by_query","auto","59");
INSERT INTO teampass_misc VALUES("admin","enable_delete_after_consultation","1","60");
INSERT INTO teampass_misc VALUES("admin","enable_personal_saltkey_cookie","1","61");
INSERT INTO teampass_misc VALUES("admin","personal_saltkey_cookie_duration","31","62");
INSERT INTO teampass_misc VALUES("admin","email_smtp_server","mail.zinitsolutions.com","63");
INSERT INTO teampass_misc VALUES("admin","email_smtp_auth","1","64");
INSERT INTO teampass_misc VALUES("admin","email_auth_username","teampass@zinitsolutions.com","65");
INSERT INTO teampass_misc VALUES("admin","email_auth_pwd","Qy63v#v3","66");
INSERT INTO teampass_misc VALUES("admin","email_port","25","67");
INSERT INTO teampass_misc VALUES("admin","email_security","none","68");
INSERT INTO teampass_misc VALUES("admin","email_server_url","http://teampass.zinit1.com","69");
INSERT INTO teampass_misc VALUES("admin","email_from","teampass@zinitsolutions.com","70");
INSERT INTO teampass_misc VALUES("admin","email_from_name","Teampass Zinit Solutions","71");
INSERT INTO teampass_misc VALUES("admin","pwd_maximum_length","40","72");
INSERT INTO teampass_misc VALUES("admin","2factors_authentication","0","73");
INSERT INTO teampass_misc VALUES("admin","delay_item_edition","0","74");
INSERT INTO teampass_misc VALUES("admin","allow_import","0","75");
INSERT INTO teampass_misc VALUES("admin","proxy_ip","","76");
INSERT INTO teampass_misc VALUES("admin","proxy_port","","77");
INSERT INTO teampass_misc VALUES("admin","upload_maxfilesize","10mb","78");
INSERT INTO teampass_misc VALUES("admin","upload_docext","doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx","79");
INSERT INTO teampass_misc VALUES("admin","upload_imagesext","jpg,jpeg,gif,png","80");
INSERT INTO teampass_misc VALUES("admin","upload_pkgext","7z,rar,tar,zip","81");
INSERT INTO teampass_misc VALUES("admin","upload_otherext","sql,xml","82");
INSERT INTO teampass_misc VALUES("admin","upload_imageresize_options","0","83");
INSERT INTO teampass_misc VALUES("admin","upload_imageresize_width","800","84");
INSERT INTO teampass_misc VALUES("admin","upload_imageresize_height","600","85");
INSERT INTO teampass_misc VALUES("admin","upload_imageresize_quality","90","86");
INSERT INTO teampass_misc VALUES("admin","use_md5_password_as_salt","0","87");
INSERT INTO teampass_misc VALUES("admin","ga_website_name","TeamPass for ChangeMe","88");
INSERT INTO teampass_misc VALUES("admin","api","0","89");
INSERT INTO teampass_misc VALUES("admin","subfolder_rights_as_parent","0","90");
INSERT INTO teampass_misc VALUES("admin","show_only_accessible_folders","1","91");
INSERT INTO teampass_misc VALUES("admin","enable_suggestion","0","92");
INSERT INTO teampass_misc VALUES("admin","otv_expiration_period","7","93");
INSERT INTO teampass_misc VALUES("admin","default_session_expiration_time","300000","94");
INSERT INTO teampass_misc VALUES("admin","duo","0","95");
INSERT INTO teampass_misc VALUES("admin","enable_server_password_change","0","96");
INSERT INTO teampass_misc VALUES("admin","ldap_search_base","0","97");
INSERT INTO teampass_misc VALUES("admin","ldap_object_class","0","98");
INSERT INTO teampass_misc VALUES("admin","bck_script_path","/teampass/www/backups","99");
INSERT INTO teampass_misc VALUES("admin","bck_script_filename","bck_cpassman","100");
INSERT INTO teampass_misc VALUES("admin","can_create_root_folder","1","101");
INSERT INTO teampass_misc VALUES("admin","syslog_enable","0","102");
INSERT INTO teampass_misc VALUES("complex","3","25","103");
INSERT INTO teampass_misc VALUES("complex","5","0","105");
INSERT INTO teampass_misc VALUES("complex","6","50","106");
INSERT INTO teampass_misc VALUES("complex","7","50","107");
INSERT INTO teampass_misc VALUES("admin","tree_counters","1","108");
INSERT INTO teampass_misc VALUES("admin","offline_key_level","50","109");
INSERT INTO teampass_misc VALUES("complex","8","70","110");
INSERT INTO teampass_misc VALUES("complex","19","50","128");
INSERT INTO teampass_misc VALUES("complex","20","50","129");
INSERT INTO teampass_misc VALUES("complex","21","50","130");
INSERT INTO teampass_misc VALUES("complex","22","50","131");
INSERT INTO teampass_misc VALUES("complex","23","50","132");
INSERT INTO teampass_misc VALUES("complex","24","50","133");
INSERT INTO teampass_misc VALUES("complex","25","0","136");
INSERT INTO teampass_misc VALUES("complex","26","50","138");
INSERT INTO teampass_misc VALUES("complex","28","0","139");
INSERT INTO teampass_misc VALUES("complex","29","0","140");
INSERT INTO teampass_misc VALUES("complex","30","0","141");
INSERT INTO teampass_misc VALUES("complex","31","0","142");
INSERT INTO teampass_misc VALUES("complex","32","0","143");
INSERT INTO teampass_misc VALUES("complex","45","50","144");
INSERT INTO teampass_misc VALUES("complex","46","25","145");
INSERT INTO teampass_misc VALUES("complex","47","25","146");
INSERT INTO teampass_misc VALUES("complex","49","50","151");
INSERT INTO teampass_misc VALUES("complex","50","50","152");
INSERT INTO teampass_misc VALUES("complex","51","50","153");
INSERT INTO teampass_misc VALUES("complex","52","50","154");
INSERT INTO teampass_misc VALUES("complex","57","0","155");
INSERT INTO teampass_misc VALUES("complex","58","25","187");
INSERT INTO teampass_misc VALUES("complex","59","50","188");
INSERT INTO teampass_misc VALUES("complex","60","50","189");
INSERT INTO teampass_misc VALUES("complex","61","50","190");
INSERT INTO teampass_misc VALUES("complex","62","25","191");
INSERT INTO teampass_misc VALUES("complex","63","25","192");
INSERT INTO teampass_misc VALUES("complex","64","50","200");
INSERT INTO teampass_misc VALUES("complex","65","25","201");
INSERT INTO teampass_misc VALUES("complex","66","25","202");
INSERT INTO teampass_misc VALUES("complex","67","25","203");
INSERT INTO teampass_misc VALUES("complex","68","50","204");
INSERT INTO teampass_misc VALUES("complex","69","25","205");
INSERT INTO teampass_misc VALUES("complex","70","50","206");
INSERT INTO teampass_misc VALUES("complex","71","25","207");
INSERT INTO teampass_misc VALUES("complex","72","25","208");
INSERT INTO teampass_misc VALUES("folder_deleted","f25","25, 19, Sport, 4, 5, 2, 0, 0, 0, 0","209");
INSERT INTO teampass_misc VALUES("folder_deleted","f26","26, 19, Sport, 32, 35, 2, 0, 0, 0, 0","210");
INSERT INTO teampass_misc VALUES("folder_deleted","f26","28, 26, Proteckt, 33, 34, 3, 0, 0, 0, 0","211");
INSERT INTO teampass_misc VALUES("folder_deleted","f26","81, 0, 10000016, 33, 34, 1, 0, 0, 0, 0","212");
INSERT INTO teampass_misc VALUES("folder_deleted","f30","30, 29, proteckt, 37, 38, 3, 0, 0, 0, 0","213");
INSERT INTO teampass_misc VALUES("folder_deleted","f30","61, 0, dfss, 37, 38, 1, 0, 0, 0, 0","214");
INSERT INTO teampass_misc VALUES("folder_deleted","f31","31, 29, proteckt, 39, 40, 3, 0, 0, 0, 0","215");
INSERT INTO teampass_misc VALUES("folder_deleted","f4","4, 0, 10000000, 3, 4, 1, 0, 0, 0, 0","216");
INSERT INTO teampass_misc VALUES("folder_deleted","f43","43, 0, 10000000, 5, 6, 1, 0, 0, 0, 0","217");
INSERT INTO teampass_misc VALUES("folder_deleted","f8","8, 0, 555555555, 33, 34, 1, 0, 0, 0, 0","218");
INSERT INTO teampass_misc VALUES("folder_deleted","f3","3, 0, Test, 31, 32, 1, 0, 0, 0, 0","219");



DROP TABLE teampass_nested_tree;

CREATE TABLE `teampass_nested_tree` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `nleft` int(11) NOT NULL DEFAULT '0',
  `nright` int(11) NOT NULL DEFAULT '0',
  `nlevel` int(11) NOT NULL DEFAULT '0',
  `bloquer_creation` tinyint(1) NOT NULL DEFAULT '0',
  `bloquer_modification` tinyint(1) NOT NULL DEFAULT '0',
  `personal_folder` tinyint(1) NOT NULL DEFAULT '0',
  `renewal_period` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `nested_tree_parent_id` (`parent_id`),
  KEY `nested_tree_nleft` (`nleft`),
  KEY `nested_tree_nright` (`nright`),
  KEY `nested_tree_nlevel` (`nlevel`),
  KEY `personal_folder_idx` (`personal_folder`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8;

INSERT INTO teampass_nested_tree VALUES("1","0","1","1","2","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("29","19","TEst","40","43","2","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("64","0","ZS internal","43","44","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("65","0","ZS Partner","45","46","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("66","0","ZS Sandboxes","49","50","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("67","0","ZS e-commerce","33","42","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("68","0","ZS Recruiting","47","48","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("69","67","Sweet-beast","38","41","2","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("70","69","Protect","39","40","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("71","67","Petsbynet","34","37","2","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("72","71","Protect","35","36","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("73","0","9999999","31","32","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("74","0","10000011","25","26","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("75","0","10000004","11","12","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("76","0","10000005","13","14","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("77","0","10000006","15","16","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("78","0","10000007","17","18","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("79","0","10000008","19","20","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("80","0","10000009","21","22","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("82","0","10000010","23","24","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("83","0","10000012","27","28","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("84","0","10000002","7","8","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("85","0","10000003","9","10","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("86","0","10000000","3","4","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("87","0","10000001","5","6","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("88","0","10000016","29","30","1","0","0","1","0");



DROP TABLE teampass_otv;

CREATE TABLE `teampass_otv` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `timestamp` text NOT NULL,
  `code` varchar(100) NOT NULL,
  `item_id` int(12) NOT NULL,
  `originator` tinyint(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO teampass_otv VALUES("1","1469697072","Oth7eeziepu3eis3eePee3uumeuFagha","26","127");



DROP TABLE teampass_restriction_to_roles;

CREATE TABLE `teampass_restriction_to_roles` (
  `role_id` int(12) NOT NULL,
  `item_id` int(12) NOT NULL,
  KEY `role_id_idx` (`role_id`)
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
  `creator_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

INSERT INTO teampass_roles_title VALUES("1","Manager","0","60","1");
INSERT INTO teampass_roles_title VALUES("2","System Administrator","0","50","1");
INSERT INTO teampass_roles_title VALUES("3","Developer","1","50","1");
INSERT INTO teampass_roles_title VALUES("5","WordpressDev","0","0","10000004");



DROP TABLE teampass_roles_values;

CREATE TABLE `teampass_roles_values` (
  `role_id` int(12) NOT NULL,
  `folder_id` int(12) NOT NULL,
  `type` varchar(5) NOT NULL DEFAULT 'R',
  KEY `role_id_idx` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_roles_values VALUES("1","3","W");
INSERT INTO teampass_roles_values VALUES("1","5","W");
INSERT INTO teampass_roles_values VALUES("1","7","R");
INSERT INTO teampass_roles_values VALUES("1","19","W");
INSERT INTO teampass_roles_values VALUES("1","22","W");
INSERT INTO teampass_roles_values VALUES("1","21","W");
INSERT INTO teampass_roles_values VALUES("2","20","W");
INSERT INTO teampass_roles_values VALUES("1","25","W");
INSERT INTO teampass_roles_values VALUES("1","26","W");
INSERT INTO teampass_roles_values VALUES("1","28","W");
INSERT INTO teampass_roles_values VALUES("1","29","W");
INSERT INTO teampass_roles_values VALUES("1","30","W");
INSERT INTO teampass_roles_values VALUES("1","31","W");
INSERT INTO teampass_roles_values VALUES("1","32","W");
INSERT INTO teampass_roles_values VALUES("1","45","W");
INSERT INTO teampass_roles_values VALUES("1","46","W");
INSERT INTO teampass_roles_values VALUES("1","47","W");
INSERT INTO teampass_roles_values VALUES("1","49","W");
INSERT INTO teampass_roles_values VALUES("1","50","W");
INSERT INTO teampass_roles_values VALUES("5","32","W");
INSERT INTO teampass_roles_values VALUES("1","51","W");
INSERT INTO teampass_roles_values VALUES("1","52","W");
INSERT INTO teampass_roles_values VALUES("1","67","W");
INSERT INTO teampass_roles_values VALUES("1","71","W");
INSERT INTO teampass_roles_values VALUES("1","72","W");
INSERT INTO teampass_roles_values VALUES("1","69","W");
INSERT INTO teampass_roles_values VALUES("1","70","W");
INSERT INTO teampass_roles_values VALUES("1","64","W");
INSERT INTO teampass_roles_values VALUES("1","65","W");
INSERT INTO teampass_roles_values VALUES("1","66","W");



DROP TABLE teampass_suggestion;

CREATE TABLE `teampass_suggestion` (
  `id` tinyint(12) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `pw` text NOT NULL,
  `pw_iv` text NOT NULL,
  `pw_len` int(5) NOT NULL,
  `description` text NOT NULL,
  `author_id` int(12) NOT NULL,
  `folder_id` int(12) NOT NULL,
  `comment` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_tags;

CREATE TABLE `teampass_tags` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `tag` varchar(30) NOT NULL,
  `item_id` int(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_tokens;

CREATE TABLE `teampass_tokens` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL,
  `token` varchar(255) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `creation_timestamp` varchar(50) NOT NULL,
  `end_timestamp` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




DROP TABLE teampass_users;

CREATE TABLE `teampass_users` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `pw` varchar(400) NOT NULL,
  `groupes_visibles` varchar(250) NOT NULL,
  `derniers` text,
  `key_tempo` varchar(100) DEFAULT NULL,
  `last_pw_change` varchar(30) DEFAULT NULL,
  `last_pw` text,
  `admin` tinyint(1) NOT NULL DEFAULT '0',
  `fonction_id` varchar(255) DEFAULT NULL,
  `groupes_interdits` varchar(255) DEFAULT NULL,
  `last_connexion` varchar(30) DEFAULT NULL,
  `gestionnaire` int(11) NOT NULL DEFAULT '0',
  `email` varchar(300) NOT NULL,
  `favourites` varchar(300) DEFAULT NULL,
  `latest_items` varchar(300) DEFAULT NULL,
  `personal_folder` int(1) NOT NULL DEFAULT '0',
  `disabled` tinyint(1) NOT NULL DEFAULT '0',
  `no_bad_attempts` tinyint(1) NOT NULL DEFAULT '0',
  `can_create_root_folder` tinyint(1) NOT NULL DEFAULT '0',
  `read_only` tinyint(1) NOT NULL DEFAULT '0',
  `timestamp` varchar(30) NOT NULL DEFAULT '0',
  `user_language` varchar(30) NOT NULL DEFAULT 'english',
  `name` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `session_end` varchar(30) DEFAULT NULL,
  `isAdministratedByRole` tinyint(5) NOT NULL DEFAULT '0',
  `psk` varchar(400) DEFAULT NULL,
  `ga` varchar(50) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `avatar_thumb` varchar(255) DEFAULT NULL,
  `upgrade_needed` tinyint(1) NOT NULL DEFAULT '0',
  `treeloadstrategy` varchar(30) NOT NULL DEFAULT 'full',
  `can_manage_all_users` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=10000017 DEFAULT CHARSET=utf8;

INSERT INTO teampass_users VALUES("1","admin","$2y$10$..EvHcr04IxaLS.QpXtbdenijFa1R9HlbJTh37bIFXCaiTNuN6I.q","","","aik0ga9Xoh1ohWae2Oech8ioNg7Chiet0zahwie4dee9faaNgi","1469750400","","1",";1;2","","1469801492","0","","","","1","0","0","1","0","1469802257","russian","","","1487801492","0","$2y$10$.5f3QasOWI5rgSwkkolxde5znfrxJcxDBAnUU1/6h8O3KPvCWQH.a","","","","0","full","1");
INSERT INTO teampass_users VALUES("9999999","API","","","","","","","0","","","","0","","","","1","0","0","0","1","0","english","","","","0","","","","","0","full","0");
INSERT INTO teampass_users VALUES("10000000","test.test","$2y$10$kWMYIxUKadYi9mOfpYmrV.1BXNmfYSQIHwgaP1Qz1t5YLHAO8nyjy","3","","Aisheif6jaecho4EozuTooxieboob7opeeLohwoh8ooquohn8e","1469145600","","0","1,2,3, 5","0","1469195530","1","o.novytskiy@zinitsolutions.com","1","4;3;2;1","1","0","0","0","0","1469197969","english","test","test","1469199130","0","$2y$10$JsDTFGbJibDYLEBZQXK7Ne.tleS5FS31TR9oStH9ie/2Ol1vkvOYy","","","","0","full","1");
INSERT INTO teampass_users VALUES("10000001","test2","$2y$10$jKkgL8BhVt/9JwCPImXFDuNrv.yVlONlofuuOk.R5m3zwyzkL/IRe","0","","Ohgoh1ahsamahy5NeeQu4Eu7uro5ahrohsaew9ouneephohNg4","1469664000","$2y$10$K5p5L2WbiOlo8noK7vOubuK6z/fLSAhnvLNPw.08xUDi2yJzEJB7G;$2y$10$jKkgL8BhVt/9JwCPImXFDuNrv.yVlONlofuuOk.R5m3zwyzkL/IRe","0","1;2","0","1469712738","1","ale-nov@yandex.ru","","8;5;6;7;1;2;3","1","0","0","0","0","1469716338","english","radafds","лоипап","1469716338","0","$2y$10$awGfPbr2is02RhOjW3aHt.OxL6jkr75h1won8pW2HFO6JsbJPEQ4i","","","","0","full","0");
INSERT INTO teampass_users VALUES("10000003","s.olytska","$2y$10$yfy.1g2Y.Ao3U234mB0SXen0iEQeDP5WLA54BXvlSof7.EgN/g0fe","0","","","1469491200","","0","1;3","0","","1","s.olytska@zinitsolutions.com","","","1","0","0","0","0","0","english","Svitlana","Olytska","","0","","","","","0","full","0");
INSERT INTO teampass_users VALUES("10000004","j.ilchenko","$2y$10$pXIQXOkrH.nSJuJ3tIuKMeERyUAoIoYX8229nLbPM3EWYStTxTALa","0","","shah7ek6ooch8ahnguwi9ViePhahw3hoon3thooghoongohshu","1469491200","","0","1;3;5","0","1469697811","1","j.ilchenko@zinitsolutions.com","","30;29","1","0","0","0","0","1469698153","english","Julia","Ilchenko","1487697811","0","$2y$10$OVZvSYd/qwDZJuJ5GkHRCOXVsRyZF2L2RTeYnrTPzwNgU09LS6uT2","","","","0","full","0");
INSERT INTO teampass_users VALUES("10000005","k.dedyaev","$2y$10$RvCkqW8mJs2PKQf3z3Sx/.9RfBDgptjIiYrPlTPb6xLBuD.IQeE4G","0","","Aimiegoolei5aighuDaCoJ5Een5oo3Saip5thak2ohneifieku","1469750400","","0","1;3","0","1469801622","1","k.dedyaev@zinitsolutions.com","","31;35;32;1;23;44;42;41;40;39","1","0","0","0","0","1469801627","russian","Kostyantyn","Dedyaev","1487801622","0","$2y$10$NzsaWQajNdcyLcInU8Q0sufhRmIuEPaHBCZutBT3uVeUAuwmfy1MG","","","","0","full","0");
INSERT INTO teampass_users VALUES("10000006","n.lytvynenko","$2y$13$546f6dbda34a4426c867auQARssYABRLgOLRzVnXdzZEgjcm3MACe","0","","","","","0","1;3","0","","1","n.lytvynenko@zinitsolutions.com","","","1","0","0","0","0","0","english","Nadia","Lytvynenko","","0","","","","","0","full","0");
INSERT INTO teampass_users VALUES("10000007","o.kaplunyak","$2y$13$59eb285612a9e6fc1583au0D2Znnk35EhW3jvrNvcqzLBxhm6Ym1S","0","","","","","0",";1","0","","1","o.kaplunyak@zinitsolutions.com","","","1","0","0","0","0","0","english","Olga","Kaplunyak","","0","","","","","0","full","0");
INSERT INTO teampass_users VALUES("10000008","o.marchenko","$2y$10$kkGVDxm3Sk4okFs7ouWoM.l4q3FCmcVeo.WZaiqmNmeTX/Q68grDi","0","","ohs5gieJeiGohPhi4ookohS5Wiesh7iliengoo5Quuolushaev","1469577600","","0","1;3","0","1469629273","1","o.marchenko@zinitsolutions.com","","10;9","1","0","0","0","0","1469632872","english","Olena","Marchenko","1469632873","0","$2y$10$IJPfvOIX8uMhWhFFFZ5y.OQ8SDXi.L8f6a4siA4njdWeKGo6/CfQy","","","","0","full","1");
INSERT INTO teampass_users VALUES("10000009","o.novytskyi","$2y$10$sbf9nOZ46JZQsV9qjWbE5ugt6zFp4AEandMAz027S3vZwje8nEL1y","0","","ve1Di1aepiviu0ahtoozeici4Aenie7nuB4cheeNgoove7wash","1469750400","","0","1;2;3","0","1469798516","1","o.novytskiy@zinitsolutions.com","","44;14;15;20;13;19;12;18;16","1","0","0","0","0","1469799907","english","Oleksandr","Novytskyi","1487798516","0","$2y$10$XSymgMGQknaEvxGRRa07qu7jCz2U0Lbfn5yvXglyzW30g7K4ek5eO","","","","0","full","1");
INSERT INTO teampass_users VALUES("10000010","o.tymoshenko","$2y$10$pa77QXjJraeiB1EbYwCvRuBWw5ZHmLSRz1rE/yY6ujlLOQXGulnEG","0","","Is4Taequipeu1eG4jai8ew7Phicheid1oeThaenuikak1xohji","1469577600","","0","1;3","0","1469629683","1","o.tymoshenko@zinitsolutions.com","","11","1","0","0","0","0","1469633083","russian","Oleksiy","Tymoshenko","1469633283","2","$2y$10$Re0NB82E.k0K7d0JUpb/KeVLz.W6p4RInGqV4xtJAaoZFMmf8Q.Eq","","","","0","full","1");
INSERT INTO teampass_users VALUES("10000011","d.tereshchuk","$2y$10$b9tYMnuT.Bf/rCZ7w8ikpuOhrmkigKRZFVcBudP6GVVkPF2lVw5lK","29","","At4ahngiec9Goibeekoh4ja0ez8xoh0Ulesh3aibie3uSaiY2U","1469750400","$2y$10$XAXzZJCF/22HPEx1yPTXC.pyjOTpacnjQ.ZnAEvCbFYuPHXMwTmVq;$2y$10$R2fK2gov6z411ezWSyTJO.CXNnmZe1zzKw/AYBLFmD5FHhIcuqh1S","0","3","32","1469772721","0","d.tereshchuk@zinitsolutions.com","","27","1","0","0","0","0","1469772722","russian","Dmytro","Tereshchuk","1487772721","1","$2y$10$Smiu1F3Ci4rX5SpupSxOJ.X7dSQepG1Z0UkW25uqDLR1ozfYSqtJy","","","","0","full","0");
INSERT INTO teampass_users VALUES("10000012","o.zinchenko","$2y$13$e1bbfe0c2b962259177deeykc8MqKIx.OPWM9yJSj3SYHmQikt1d.","0","","","","","0",";1;2;3;4","0","","1","o.zinchenko@zinitsolutions.com","","","1","0","0","0","0","0","english","Oleksandr","Zinchenko","","0","","","","","0","full","1");
INSERT INTO teampass_users VALUES("10000016","o.trelin","$2y$10$lj8tf0cFrm/du36mrplGmer.ghakNeGeartl11y2Ss20n5EhHsip.","0","","ui1aopei1ju5teiquaw9Deelaecie5eesei5meeb8ohxoiFa2d","1469664000","","0","0","0","1469785353","0","o.trelin@zinitsolutions.com","","43","1","0","0","0","0","1469785388","english","Oleksandr","Trelin","1487785353","1","$2y$10$KthkuEo02TKyNQlc/hAyDewqXWb95JP75NE4p30yuSt/1UH9manRy","","","","0","full","0");



