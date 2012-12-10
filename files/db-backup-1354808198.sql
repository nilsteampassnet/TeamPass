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

INSERT INTO teampass_cache VALUES("1","it1","cuicui","test item ","1","0","","Moi","F1","2");
INSERT INTO teampass_cache VALUES("2","it2","","","1","0","","","F1","2");
INSERT INTO teampass_cache VALUES("3","it3","test","","3","0","","","F1 » F1_1","2");



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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

INSERT INTO teampass_items VALUES("1","it1","cuicui","7T4t5bcO3Gk0QxMbq/xStdCpBsTILwL1IRnxpdef8mw=","http://www.sdsd.net","1","0","Moi","0","","0","noreply@qsd.net","");
INSERT INTO teampass_items VALUES("2","it2","","ef+iu2lTR20hfhmKGwWFAB4viG+Pk1JqQh9KRZK+lhI=","","1","0","","0","","0","","");
INSERT INTO teampass_items VALUES("3","it3","test","6Vc3d61JslQDsWlnTolT6Ba2Q5DZWYKb7eGhHROJTEE=","","3","0","","0","","0","","");



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




DROP TABLE teampass_keys;

CREATE TABLE `teampass_keys` (
  `table` varchar(25) NOT NULL,
  `id` int(20) NOT NULL,
  `rand_key` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_keys VALUES("items","1","f47d166f59a31f6");
INSERT INTO teampass_keys VALUES("items","2","78aeb13d6a098c9");
INSERT INTO teampass_keys VALUES("items","3","29fc241b3557b3e");



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

INSERT INTO teampass_log_items VALUES("1","1354523267","2","at_creation","");
INSERT INTO teampass_log_items VALUES("1","1354523269","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1354523309","2","at_manual","test ajout nils");
INSERT INTO teampass_log_items VALUES("2","1354524005","2","at_creation","");
INSERT INTO teampass_log_items VALUES("2","1354524006","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1354524011","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1354524014","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354535387","2","at_creation","");
INSERT INTO teampass_log_items VALUES("3","1354535389","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354536021","2","at_modification","at_description");
INSERT INTO teampass_log_items VALUES("1","1354536028","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1354536030","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354536035","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354536057","2","at_modification","at_description");
INSERT INTO teampass_log_items VALUES("2","1354536363","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354536402","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354536711","2","at_modification","at_moved : F1_1 -> F1");
INSERT INTO teampass_log_items VALUES("3","1354536718","2","at_modification","at_moved : F1 -> F1_1");
INSERT INTO teampass_log_items VALUES("3","1354536892","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354537003","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354537064","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354537149","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354537236","2","at_shown","");
INSERT INTO teampass_log_items VALUES("3","1354537241","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1354537246","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1354537249","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1354732756","2","at_shown","");
INSERT INTO teampass_log_items VALUES("2","1354732902","2","at_shown","");
INSERT INTO teampass_log_items VALUES("1","1354733189","2","at_shown","");



DROP TABLE teampass_log_system;

CREATE TABLE `teampass_log_system` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `date` varchar(30) NOT NULL,
  `label` text NOT NULL,
  `qui` varchar(30) NOT NULL,
  `field_1` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

INSERT INTO teampass_log_system VALUES("1","user_mngt","1354521367","at_user_added","1","2");
INSERT INTO teampass_log_system VALUES("2","user_mngt","1354521591","at_user_added","1","3");
INSERT INTO teampass_log_system VALUES("3","user_mngt","1354522441","at_user_initial_pwd_changed","2","2");
INSERT INTO teampass_log_system VALUES("4","user_mngt","1354522565","at_user_initial_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("5","user_mngt","1354522566","at_user_initial_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("6","error","1354523789","Array%0A%28%0A%20%20%20%20%5Brecherche_group_pf%5D%20%3D%3E%20%0A%20%20%20%20%5Barborescence%5D%20%3D%3E%20%3Cimg%20src%3D%27includes/images/folder-open.png%27%20/%3E%26nbsp%3B%3Ca%20id%3D%22path_elem_1%22%20style%3D%22cursor%3Apointer%3B%22%20onclick%3D%22ListerItems%281%2C%20%27%27%2C%200%29%22%3EF1%3C/a%3E%0A%20%20%20%20%5Barray_items%5D%20%3D%3E%20Array%0A%20%20%20%20%20%20%20%20%28%0A%20%20%20%20%20%20%20%20%20%20%20%20%5B0%5D%20%3D%3E%20Array%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%28%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%5B0%5D%20%3D%3E%201%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%5B1%5D%20%3D%3E%20tooquohx%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%5B2%5D%20%3D%3E%20Moi%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%5B3%5D%20%3D%3E%201%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%29%0A%0A%20%20%20%20%20%20%20%20%29%0A%0A%20%20%20%20%5Bitems_html%5D%20%3D%3E%20%3Cli%20name%3D%22it1%22%20ondblclick%3D%22AfficherDetailsItem%28%271%27%2C%270%27%2C%270%27%2C%20%27%27%2C%20%27%27%2C%20true%29%22%20class%3D%22item_draggable%22%20id%3D%221%22%20style%3D%22margin-left%3A-30px%3B%22%3E%3Cimg%20src%3D%22includes/images/grippy.png%22%20style%3D%22margin-right%3A5px%3Bcursor%3Ahand%3B%22%20alt%3D%22%22%20class%3D%22grippy%22%20%20/%3E%3Cimg%20src%3D%22includes/images/tag-small-green.png%22%3E%26nbsp%3B%3Ca%20id%3D%22fileclass1%22%20class%3D%22file%22%20onclick%3D%22AfficherDetailsItem%28%271%27%2C%270%27%2C%270%27%2C%20%27%27%29%22%3Eit1%26nbsp%3B%3Cfont%20size%3D2px%3E%5Bcuicui%5D%3C/font%3E%3C/a%3E%3Cspan%20style%3D%22float%3Aright%3Bmargin%3A2px%2010px%200px%200px%3B%22%3E%3Cimg%20src%3D%22includes/images/mini_user_enable.png%22%20id%3D%22iconlogin_1%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27login%27%2C1%29%22%20title%3D%22Copy%20login%22%20/%3E%26nbsp%3B%3Cimg%20src%3D%22includes/images/mini_lock_enable.png%22%20id%3D%22iconpw_1%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27pw%27%2C1%29%22%20title%3D%22Copy%20password%22%20/%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_pw_in_list_1%22%20value%3D%22tooquohx%22%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_login_in_list_1%22%20value%3D%22Moi%22%3E%26nbsp%3B%3Cspan%20id%3D%22quick_icon_fav_1%22%20title%3D%22Manage%20Favorite%22%20class%3D%22cursor%20tip%22%3E%3Cimg%20src%3D%22includes/images/mini_star_disable.png%22%22%20onclick%3D%22ActionOnQuickIcon%281%2C1%29%22%20class%3D%22tip%22%20/%3E%3C/span%3E%3C/span%3E%3C/li%3E%0A%20%20%20%20%5Berror%5D%20%3D%3E%200%0A%20%20%20%20%5Bsaltkey_is_required%5D%20%3D%3E%200%0A%20%20%20%20%5Bshow_clipboard_small_icons%5D%20%3D%3E%201%0A%20%20%20%20%5Bnext_start%5D%20%3D%3E%2011%0A%20%20%20%20%5Blist_to_be_continued%5D%20%3D%3E%20end%0A%20%20%20%20%5Bitems_count%5D%20%3D%3E%201%0A%20%20%20%20%5Bfolder_complexity%5D%20%3D%3E%2025%0A%20%20%20%20%5Bbloquer_modification_complexite%5D%20%3D%3E%200%0A%20%20%20%20%5Bbloquer_creation_complexite%5D%20%3D%3E%200%0A%29%0AjGS8UGZmZmYSJNn2UuPe1gfoOCPe6xwGc2rjFdVLmElfll5Px9mC4DVX Xlo/ 3emERbi/QHffPbyz9fRiIJPesMVidF bmHUK/FV34cpLkDdNAEGmRTjSEJxnrtTGTkGDKSYU9g9w5iJRlystHlB5GT2bTHKMSfNxGLmmfyxpcY8IovsE5urNZ3En13Cuw8ChHRY/ph8YlOV1T7eHpLeuC0E7glPKUbPOmAhviXixE/BtEyb1lkx6Q4T6AS pK4zoI2AzO4cZo/qs8kqjxjHpqWTXWO2EI2GjRT2x5ivmSw3zujv/IOM0QdQkmdCyeP49uR6KoKt8YJnvouJ3hI4FCtjmBVe94vNDKvrgGoqjNE87iU30FCBTxCcEud7767Di6OH1fJav3uS3AXUv9LhPzq169keEB6NOEFjYX 9wqTUR6ROrzElyXtmJIjnRyTTQk7FZxkZLK457KAh2650xnVTrg84/qH/VX9HpPYHxQM6RNe3dblyBDW45lJ5Hc44yiICUFgHuz3IIFwS653sAXJYre7NVsZ0zDHNPdxp5p/lXgS1U6m6s9FRoDksrGDBmKHxxU1o6a4dFuvI5VF34/n3oL9T7LCgRsKSF gLAl6mVXS4otHDBTRrO8o6XfnXVd45s5EJ93brG1liFuTu8GGsDK fN8 Vyvgpr7AggeXADhhMQAph46s7ERZpcNhW0ihcOe3/6ON1BlGOCIZmpebrc Wp1V5HRu2KQagx0uRGh/hbq5baQDvBNkDKIY lqBNx5g2FeRoELUawYkJC/N1bVA0s3LbGd0IOBAY2P5FdF4Uy6a3cBx6pIiKSllzh4cGP2xec IKfoVTKSNUBEXSHI7zwQ7uRXC01EIl0Z4pJsHXSi5icVLho2 gXVntzFySQmvxIiy XLgpODmZLYIzj7JLk4xrvZbz6ra//O1ACZzoeu/Y7wJcS8W9ODSKBumC85UzFj5wTf8F06Pd gjpixhld/wRrG3Bmyd7wJ04bXhR14MAxGvhZ7HnyAsbKfAlKjQ0gmQtQLoI1IKQRPV6qIS4lga3me9r kG5ypuBOISNcFNGp4ra1/W4o7M7NKvagsSm1qTmxhBIEdQabbqIsB7ueEZqRmYPuqyBo9D1RxpJYQF6z 9Jh5FuIqOz7YwJzPmsD00EI6M Say i6MjKBjCODTV4kgsvOcChvi32zhqwqMFjpL9h/vGGYHrdwk7BLNvN2FEVKo02nRsKVOqz0JXNZC0KXUQf eQcJ7VudXlm8BgH3lx36COBNaH97MlCD05jJJZHgg4SJi70h9obsesxfvSMj6plE TAfTQXsyQskbOE0 epHV6i4KiRil8DmZ3Q/uqEC4IewAcb3pL7EARbUh5cBQmt6qb1XVTG4eIAJE/VAHga74i1jWZyptpaXmCoHyHpxGDDAtulbWgNrgFsnL2BDgxh143Nr6lBdOm0WJE1y  yucRUrI/U89p0Tf766PKYJmUI6cIV83pv9OXq7UlS1n217b9ZvWTioi9rcuppJCd1hn6co459uy5qYr3n4skYEm1WPD0EV76tfPpZgd76KmjZ1T75W55i E0b2DiW8DvIB79TahtOGHAL0JFEsq8i61mPK6EdWTWtCQWbfI017tVPZtnUMkXVdFNcbPxRZw1yECbNwj4vW7r891gPJNQGP/Mu Og4hsM5S2m6fc2OaME10tnlFXjdAA0Hrt2EHypbPzeMB3sKpUc FTYyBOOhS/8zHUPUkfdL7FqGM JoG4AgIwsEDnvoBi4iTSQl5YoMfmXODnDchlzba ercjFUWc69h9ibVp1lUOF4X2HNiO58nciqjtBrYbNSEjrpb1wUS8aje7XMcEk71hK18/zpQpv7ZXQXUGZJGAhA8VCyWt4/NyV8nOo8CPd3Q2QX4LKYOWxDZuT76urqEl1sCaeSWC2y13CiR39xZ/GlnoyICYRGKsawE06nG8Nbb4xWmROoOEorwfgNRqzSzJUH8gaypMhFsKasMHPlCnMpILHHwZ8LD7YB31 H8QoCDHrIiB7bDfznkXJEYA0Qhzj0BCuMBzX4CRaa5v6vghMOj0 RcRidYReh L0 lohym82qs7qiyDWI hBgNUYyt7cewIwxsTPV4HAPbGMePCObMhT5yKzjcu6kXm1x/w8dH7VYY91WBps5 XI3FGhcax0OjEtq2ZcyEorXSSpA d2GrmsGMcwaDjz XzSUeFtI fkqV3mbFttTUD2u Fqtn2rqG EfUJ6ln  aDXClKDFlSVh7vr8YfQZPbB anYjOTCwqDkLdinAIdOv9f7fJeeVlYBWaqG8VmfLGdrRjBggCWAKW9zrKxeEcYt9xsoSZxl4cFvcAX1VAj3u4vbHAQSJ6WcA7BxWNjUW3uaIGc7ufH4z74H76PVYQ2IAevE7dwGXZM3k5KkFtI6ampXNmxGuRMCLqeKhAoVUVhOd5 /JoRrAkOxPyr7xJSR34VYmx4q2l4M7u0PFN2RyRKXvqGRxSFDnPdngMm8RjCNM0SMDWWFVeKXFh4tu2aFNjJ8cSmKtCgKE 244mh2oOk9GjAQzv70WzRBQX48q0VeQ7wH jOax8Zn13YuYp6fxuLYV6Erol62QPcVSxPDy9aZmoXIppBlV72NerKVpUPNq4ydVM3Ic0ocxqU1zNncdI0bqjtP4Tp/vn0ycLuhZzpSUSqn0F3mF63BciLnr0Hyukzah3ihBVDtYVza5oJfUrDu6otsu2HHJkzYXddWFBtrMU1P1OCckiGT7YdwI2Momz/f9UQKKZwmYm1tvTqZO/GS0Td7KUuXBxPFp50OKFbWIxMy6xrgwLjN9DIs11xHQ/2 aY2SmmH ZWWl7pTjgQOwgGuTk6CIxO3Fgp30H8UPmPw75Y7Sbpz9M/4l5w5bck3qmLxiROZpLFhThDyCz/VdM6zlQV7Xnu9NRIclUIw1wg9sRzuD0QjohmAxB6J0QxU5iaBdNaTC9Z5q xfGcH5BWCOEYSW9glvCzxr8RyPYAhFSA5jczAeEUPtCPNOMv9FlBSNxso2e7sjFGdewjXPLzN03k/grSWA8ElHJkUWMOz7EHh9P3352O4lCjRA%3D%3D","2","");
INSERT INTO teampass_log_system VALUES("7","error","1354523904","Array%0A%28%0A%20%20%20%20%5Brecherche_group_pf%5D%20%3D%3E%20%0A%20%20%20%20%5Barborescence%5D%20%3D%3E%20%3Cimg%20src%3D%27includes/images/folder-open.png%27%20/%3E%26nbsp%3B%3Ca%20id%3D%22path_elem_1%22%20style%3D%22cursor%3Apointer%3B%22%20onclick%3D%22ListerItems%281%2C%20%27%27%2C%200%29%22%3EF1%3C/a%3E%0A%20%20%20%20%5Barray_items%5D%20%3D%3E%20Array%0A%20%20%20%20%20%20%20%20%28%0A%20%20%20%20%20%20%20%20%20%20%20%20%5B0%5D%20%3D%3E%20Array%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%28%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%5B0%5D%20%3D%3E%201%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%5B1%5D%20%3D%3E%20tooquohx%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%5B2%5D%20%3D%3E%20Moi%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%5B3%5D%20%3D%3E%201%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%29%0A%0A%20%20%20%20%20%20%20%20%29%0A%0A%20%20%20%20%5Bitems_html%5D%20%3D%3E%20%3Cli%20name%3D%22it1%22%20ondblclick%3D%22AfficherDetailsItem%28%271%27%2C%270%27%2C%270%27%2C%20%27%27%2C%20%27%27%2C%20true%29%22%20class%3D%22item_draggable%22%20id%3D%221%22%20style%3D%22margin-left%3A-30px%3B%22%3E%3Cimg%20src%3D%22includes/images/grippy.png%22%20style%3D%22margin-right%3A5px%3Bcursor%3Ahand%3B%22%20alt%3D%22%22%20class%3D%22grippy%22%20%20/%3E%3Cimg%20src%3D%22includes/images/tag-small-green.png%22%3E%26nbsp%3B%3Ca%20id%3D%22fileclass1%22%20class%3D%22file%22%20onclick%3D%22AfficherDetailsItem%28%271%27%2C%270%27%2C%270%27%2C%20%27%27%29%22%3Eit1%26nbsp%3B%3Cfont%20size%3D2px%3E%5Bcuicui%5D%3C/font%3E%3C/a%3E%3Cspan%20style%3D%22float%3Aright%3Bmargin%3A2px%2010px%200px%200px%3B%22%3E%3Cimg%20src%3D%22includes/images/mini_user_enable.png%22%20id%3D%22iconlogin_1%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27login%27%2C1%29%22%20title%3D%22Copy%20login%22%20/%3E%26nbsp%3B%3Cimg%20src%3D%22includes/images/mini_lock_enable.png%22%20id%3D%22iconpw_1%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27pw%27%2C1%29%22%20title%3D%22Copy%20password%22%20/%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_pw_in_list_1%22%20value%3D%22tooquohx%22%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_login_in_list_1%22%20value%3D%22Moi%22%3E%26nbsp%3B%3Cspan%20id%3D%22quick_icon_fav_1%22%20title%3D%22Manage%20Favorite%22%20class%3D%22cursor%20tip%22%3E%3Cimg%20src%3D%22includes/images/mini_star_disable.png%22%22%20onclick%3D%22ActionOnQuickIcon%281%2C1%29%22%20class%3D%22tip%22%20/%3E%3C/span%3E%3C/span%3E%3C/li%3E%0A%20%20%20%20%5Berror%5D%20%3D%3E%200%0A%20%20%20%20%5Bsaltkey_is_required%5D%20%3D%3E%200%0A%20%20%20%20%5Bshow_clipboard_small_icons%5D%20%3D%3E%201%0A%20%20%20%20%5Bnext_start%5D%20%3D%3E%2011%0A%20%20%20%20%5Blist_to_be_continued%5D%20%3D%3E%20end%0A%20%20%20%20%5Bitems_count%5D%20%3D%3E%201%0A%20%20%20%20%5Bfolder_complexity%5D%20%3D%3E%2025%0A%20%20%20%20%5Bbloquer_modification_complexite%5D%20%3D%3E%200%0A%20%20%20%20%5Bbloquer_creation_complexite%5D%20%3D%3E%200%0A%29%0A/2S8UAYGBgZS34y2pLF7eo2aCiXLbYHEzbYtf8w2hQdvDSI19dEb9IzaMS1XInUc9fVgNWX7wbZk0jyAXA/qgrge5kQXRLVjkzpJLqd2WnU O1FgTadtyd2ncqz2mw1EXWCsdohGO4R2EiPT8MlamL4bwfLlde2dA0AmMk5Xp 7ES3iRvhPpifwoGRy3/67krso SELvpBZaglVee4bCc5/J2mVVi6TBDhlbzTVf9EhNAIVCbMi2E5oDBqKFv1IKH460J8UydjKHCre7qeCMTLR0M5Ejl60qOgl6xKTQXpuVHVAlAowIpcY8i4XfA6vUy50THgEAcNoi7RDRM7S0I2JUjJF2OKyGYyfJzfUimw i3g68PUT7XVGBMkB2cKl2oAlBA8/wwY TS35XP/kdScaXAmZEDP9jA6WvCh8nvydPLnZqK7dW7P5kPapB95I90ODF3Y2evrKWtF9TcHWZ5glFcGs k9EYMC4jI3uD/7hH cjcr 4J0mbkvBQdywIox8Eo2/ngMMGJMwzeSdOZ2CSpMDETH sUp5be3KGGN0rQwYyclfACwdDYJlXup9dFHTihY6DziALorVU4WMDlVI0pDjEUke653JYzl5uJo4qO0VblnYY6QH/OqmUdoir5vstxxFOjMRvvQiN6zNR3DTBwjp0z1MzzBSwis clsjJz8p8FHoWBTKTM1yOoISA71CWvNrjfmdCivd cee30tQ9 MNXJHz77bkmFRHGFjTp9CnPN6Xevdxcy1F8WqcIyll4Rh4BMFJcNY3zhvDeeq4hes30f/8dVYScuUC3/I3dafYyIUfGn2l6EjbtwTjKmXsbUNmK9mN6vriz/aptVKXy3OwGf6gLjbeqO8t3ACh6dQ/phtHh81AIP6xbAO6c20ZVlqJC9h4MfvaHMuZmtAekRvAXVVmLHznHPeC2buvb8LeSyEeb7MmtEj/zScguAAuha9rQLIDAT7v31bG3StxFig4Az BklJ3IXeRBCCBm6/sQQbSSbPDI8/iiy9eCZougxDIIH8K7qGcJDd5MGe5AGqVICydQFZgin2bTmVfXEU94TMbtUvKubN93jhKJuRc OXtiHbexwm/QglHfZL1135qm12kczrPor0V kjSO/alfApMZL6zyr GbBvs49QYens7GhA6xPfd1Pk2QbeccZsFcIAM8ruF2e6mtB zRk5qk9VdmaoXX3wH f8p/5rfJ9eqUmVJhdn0pkUl4v9iBy82awV/UndcJICk VQPNDEGWrtrMQ NXdO1gqluQNVmp0vXit5k9IHLYTU4ITqwBEHF6ZZhbSqsCn6C7tfbg2doOx3n6PTXanFosyKHqqAIJ6I4BApajy9 eLvRCeBnEI57B981XjGk5L9KWsXovYzPIBMO8DUsUm0QHjlWljTR5L0HpEqDYC87XHboilNMaprCf1J2tmXE/Gy/4g9Xz8mzt/MEBfuo0QRTZaTpx1YI7ObHpZYDukaXbvthseWiKJBFe7f5 qxMEVQQ0HH1Sp9t 7vkwaEXVlJtM0wxS6CQ0YK40B8dvKUJXOIhFLnttRHiAJ1GLXka8xacA QLi5OhjP rlqirVoFwAQr1jx10DVbkolJaCXUrZDnn1bJ6fhn0Czb4IGH/pI ItXc1rXszkxozY5k3ANCmJMQugGbMwrcEKk5BK2kRvcmRjDLL BnInbXLPm1cI 3aXq92E8I2UI90siWq5PRqksZLHr59bz4A4Suqu9TX0Woz9Vv4GDHUS7tLsUoUFmPYokiOsXrtETPvQf3zyAPOshIkWYpGr/ZtpbsY6uxtbVSZOhpGehEkG8ttkc0Rdx2eCm1NFiLgzpPawPiyO1GdzIZZSIJ/q3qITLGbpFKybz8trCFvW3Vgli5grJ5GWVEhxpfJEejButGcrf UJYRtVg2SDdc8zzVxJBTOqJr wE IFK8TXOy4PdnQMCe0OnN53b0aG42D8odz8li7MiyZmlcwccfncgniOmqD3HmcCstkKaItYA82n7o1acYOkvdmtff00cuIpLlJX5qCN7fnjmtDx4ztTVbgOJFvWOGxM0nozghEsb6pVN PdC/pTEfb3ASEm Eca2XfWJoauunsm8y4geDGG7 Rt25y8Qcn82FKjXRJ4N9x2CFd9jV3zS7 lrnVRYuVeN4e8U9BA JqjLy /T4xuQpMGi/VVWwP39rBwAkPtQ2y8I55GeeKStwSGx9iXxk49OBKOZKrf1ODtCyS51TjBTkBrfd/oMS4Q2d8BgxSXMygBWAy089IGTHkkrcWgAMc0umvVasv/DX1ts6w0Vgepr9NCRfVlLtmVB7F3kKz2dPpNZH9o bN7nsS2Hq9 QoWz0TgZu0DGXNtwN2K0P4jIFvkpVlK84MChVjKno/zW7wFPtmadg/DnQKXva3IIfzc4olp7UsQFo aLs68ytJFRIbqbyqee68n0J8K0HOPduQMa2IwjW4UUvr3T259YrVN8xx9VUNEsj4UDdW2nOm6eWAVeKR/ggGGWa2awsvaR6ME9OC3ZcF9AkbvJ3aIl1mDC8o4ytaDzhIa4u 7j5K5kyR/gjOJJlj7p64rxcJ7 oTdWqqR1gxjBGxPie kwZqdUwF04W64qU2XDuZoDRn1Kq/CzVZgNgUm3di ReWIiJq1Yg1m7RYBkb/jYLqw5JpiCgUBNrkySzC76R5fnWFkLl7DvhNpt2fJO4eEirsMjyao63J/hRmSQybCeyZ6 gmurlCD2z VzQw1FNRiFBf/oYIST kZ61mVnJwo55Gsdj1yIk97qAjqpaWlM3P8JGXe8848TMlovLcwt12BBJBUztvaddFyPWMfr1TATnyq1W4GVaje3u4IX9HC7 2W2XDMU0VAHoonOoVQqEM532waNF0MQeuQgUBV7xgLSqBSVUHPDlDWex1uJaiuj1FcgVDKSp9VIj7KDewkoV760OpaFuHtRWVkBS5I8T4LOvDwvRAG2Q22mD5lidoA0sPlxQ2ewGbij/gCnV69uuWZ/5hujh6mzD0R4wBPlctUpbJnBL C73pxnwsaaIcoTle27TYYR5SI0masqZVIZabissRGlXjgUJSpeudBnQA3qgkg%3D%3D","2","");
INSERT INTO teampass_log_system VALUES("8","error","1354535525","%3Cli%20name%3D%22it3%22%20ondblclick%3D%22AfficherDetailsItem%28%273%27%2C%270%27%2C%270%27%2C%20%27%27%2C%20%27%27%2C%20true%29%22%20class%3D%22item_draggable%22%20id%3D%223%22%20style%3D%22margin-left%3A-30px%3B%22%3E%3Cimg%20src%3D%22includes/images/grippy.png%22%20style%3D%22margin-right%3A5px%3Bcursor%3Ahand%3B%22%20alt%3D%22%22%20class%3D%22grippy%22%20%20/%3E%3Cimg%20src%3D%22includes/images/tag-small-green.png%22%3E%26nbsp%3B%3Ca%20id%3D%22fileclass3%22%20class%3D%22file%22%20onclick%3D%22AfficherDetailsItem%28%273%27%2C%270%27%2C%270%27%2C%20%27%27%29%22%3Eit3%26nbsp%3B%3Cfont%20size%3D2px%3E%5Btest%5D%3C/font%3E%3C/a%3E%3Cspan%20style%3D%22float%3Aright%3Bmargin%3A2px%2010px%200px%200px%3B%22%3E%3Cimg%20src%3D%22includes/images/mini_user_disable.png%22%20id%3D%22icon_login_3%22%20/%3E%26nbsp%3B%3Cimg%20src%3D%22includes/images/mini_lock_enable.png%22%20id%3D%22iconpw_3%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27pw%27%2C3%29%22%20title%3D%22Copy%20password%22%20/%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_pw_in_list_3%22%20value%3D%22aiheobeo%22%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_login_in_list_3%22%20value%3D%22%22%3E%26nbsp%3B%3Cspan%20id%3D%22quick_icon_fav_3%22%20title%3D%22Manage%20Favorite%22%20class%3D%22cursor%20tip%22%3E%3Cimg%20src%3D%22includes/images/mini_star_disable.png%22%22%20onclick%3D%22ActionOnQuickIcon%283%2C1%29%22%20class%3D%22tip%22%20/%3E%3C/span%3E%3C/span%3E%3C/li%3EZJK8UIiIiIjchjw 16vIIGuICC8xIKFsslP7qnejrw7bPknL o7JIRRWQN/3cb2Xot2To6u7a6Z7B5HmEkNOkGBGI fwmHFLRQN Cd JlwuBLHPQy rw6yG/52zYBifsEQbxT8DGUa8E14TMstGfNbGtszClo3k7lRSYmUDSxiMzkpJSyt0IkVrpz7/mg8KhAzF6MYwa3fHqwKLqbpiXNBgHY4VW8pFQf34x83ciY0dfKkLJ6e9Sx14bbwOFvjiRVlYFkk309W0nW/836NrYpGUxeq3Hyl7895b7IVhSXUDa1YZZBY82VX24p9wJFxfF5Z6kD tLGzQorM99srRBpO Pe0/VfzLF9Gurv98IZg15hgmJgd8IlO19aqA7DDAImRX43mbfkZENdh1k RikIuzFX5uBnITYExEo9Pq nqETJbWUgHBET3bQoS6nptZR1kbFI8nHQeUJAtA HvrgCLbTRfyiYvtSJ2TvRtNvpTcrb2h9KPZPrn4e3NgGHjR82rTQhHQIy6iE5TvPFAZN7 jrWjeBNd9y1RkzqsghLU/SE6rMzVaqtWYJqrg7ZSYEqzddfh5hKih8wGo2nlTKNO1Y0f4Vm8SD ibyjOaIyvraWxgCsHuVre7Of2tmvh6OsVTpj a1NOGk6fqTsBYHFucxYFUdkKzr17F8f3oLwdqnsTt34F4VxKqMfdrPYMyBAFRTOlWW I lYbwA2lGzD2lX53HQSkCyzWclYUVGC6qxtVAfAzytUD/xyvGOiZpcPMUehfGy3kB6QrlbrjuR0RWHFGjV4wgPeTOOJHDRpy0NUUmFfFgft09SYAnaeNXV7E1b6 N5STLSlkM0rkwICu2Kqr/Znxlehs Q6zJcUBzlCxPbnhGkn u8 7NMjH9ewjCzh4NX1KJ0YWicwyTORr4tstK4 Gx3 nn0eDfPgipSPLIYnJyIHO6E9IKjyPzXzZR11/Ar7socDfhDNmTds5cXmN9ZSe0Dmar7Sy7jW1cdx6JGkRqtKLvpe3X6B2YzJ014LRxy7ga zuhyiVrSjm2NAar8RMondSBTJrDpp/r0WMrHJXpSxarWP/Ej3yAT7GeQQSh7a4wFt1LUIeomaSNKEGy8EDqQ72RRoVDZ123M93fIZVtroCPdm0oDyT6wVxVjDiYP3DzGqG5jZvbqVYheEp8pjkd40/fucEykes4HOI/0 qzaE QzeAWGytzd9IYtlNd8BnV q6j6dTpGyfZBxsgXXbejErMB8bwAKJRxYMb1psKY77dOEvoc82MA 7q0UspX2/Vu 1MOpSsL4QU5xGVh3xx1F6xs2qvfPSB mLgRbD4GARdAutEptcNSpsdiwao3O8bdNBLr7tJ79Hi32ldbJAHx33x2aODaRxHAqcVwoLW x/NVW740OpDhTL184WXsu9Z yPKYZ3NzVjbKGUqmZhCeP1Gq4pU6 VrV7v7b6yJMaBOktX3L6gENvlnSKOmybA6CrHfZmZETxVx PbYArrXBXAQx0q/Ivj XQpVUGMJ A/KkrH31nwJTgUT8JUkcaFH5MCeTQ/cLC9qzpPJ9kmnQBoXymmAQd/tuT2UZpI9EnmxKWw6P6jSB0hoNl8TwCM//7gjsv6XjQRaRJWRIng0ihL PmYekKNDGTlEISURxFYkYtBXsqmLXhzKQxnFHpfcNlkuyg0EQljhyIeSSkmlT1x7R7zzv9 sy0XKZArldQo3AsERw Cuqx5kBZgz1APWYfTej0xlzk ALPMSNWLS qL1nreOWEZsNxSw6sfk FIgReuBdobln1znd5zf2oXaS7Ud5m2CB1F7Zj i28gcL4E2V1dfKA3zH5Cd8Q5vUVUusz3hYnQ2ZwU7LzilxaE0a50Cl32285mGeARdyPoLaYUXhSyBXpmN6od1bhcXwpXBU6AZF/MPjftZ0oIbQ5LCO7NHV6STLscsWay3lg7bD yhJsN8j9sC jdvHq1aJwaBf8SK4Vkah0BkvMN/0RHpztOEvml3ELNVu151h1YQIbJ1QC/EFsjYHLDKZOjX /jDx4FERP 9uZbm1kQSmmGK Dg9ivLft/i0IMau7kHcgJVnTBOzeMxr1Q8MHMNdZx043EN1uXHQDoVgAsRxLf6itmpqDCYZB1hzv3uRG5xr8pehdjGSB2fnbRejxhWg16h63K794WnZjWgRzsNVCf1lelQPzYff ydiLE4id4dstQ1pW4UYgaRm NlBJcLO0s3UaY9gG5vMAOn/2II7En/wjO45/KeFbDbueo2CmRrIVH/S1KuLHt9hUoKH RTujnn4LDLPSykNQfHJTrp01gmI9mpbJOVCrLVWUo/TNdLgaMPN00xhMQv5dtuImnEz Nvh4P3uHQHQfejn /60zPDGq2nA7cuY3uyS/R5rcfzog xz3uTvSZrzzasVFtI7GZsEGX2 M1g4TQjPNsB4NtPkbdPeQTo3ua06dbZ54COhnG3LDp Z4pGBYf3AydXNpwJEHiO9DpEPyGHVOWlqj9oEx2Llh4GMQaeC8tn5e8lIA0x1kwFxJ07MUGnQ2/LL5bIiIdIoB4zoK3j8ggjWPCP9i0caukTPmmzjRGlDKzlouyuL6AEergy0/ Ap2eDeNll9h6kLAHMaJ69xNOI262ztFWzWniLlD3crJgi9IF7cAFKDbrN0g VPVF GwKPJWLkeE2Nz7T2Uddp/Cihk7F4xuCEdnUXYx2opwy1cqC5dnKwT mfUIYesKkf0DXiBRKuiBJ9XINplmIQ3k8coE5b2M9cISW/bFwFs7K1A7mAUlGAYe6kF 82nItRVB1mPrrauRUstgbYl4T059KLdqZjt8fzSW9Y7WRyLr3f2nmM7S3p3J/3CS/m1O8crPVOt6rXZFIKZsctbLu1SSfB4 GXFyqJuaGWpunBwkQQgkG2liIQsxWS9v3DtTQImjjGj7Km09AhxvPikRsezZg/fXS8d/LQoH8 REX85dRnQSV0p306 ttP8diM0NSESN1/9zsBvESa8zsFz mtYlcnrRmQFmlgXH5jmBnWd6z006OVore4LcJOKrV/Qly3w oeh4S012VeyBQna8aYdbPQLeLO9DmJIEDAxQUx7wmmVioidpm6zgnG/lNtLjE0SEmw%3D%3D","2","");
INSERT INTO teampass_log_system VALUES("9","error","1354535703","%3Cli%20name%3D%22it3%22%20ondblclick%3D%22AfficherDetailsItem%28%273%27%2C%270%27%2C%270%27%2C%20%27%27%2C%20%27%27%2C%20true%29%22%20class%3D%22item_draggable%22%20id%3D%223%22%20style%3D%22margin-left%3A-30px%3B%22%3E%3Cimg%20src%3D%22includes/images/grippy.png%22%20style%3D%22margin-right%3A5px%3Bcursor%3Ahand%3B%22%20alt%3D%22%22%20class%3D%22grippy%22%20%20/%3E%3Cimg%20src%3D%22includes/images/tag-small-green.png%22%3E%26nbsp%3B%3Ca%20id%3D%22fileclass3%22%20class%3D%22file%22%20onclick%3D%22AfficherDetailsItem%28%273%27%2C%270%27%2C%270%27%2C%20%27%27%2C%27%27%2C%27%27%29%22%3Eit3%26nbsp%3B%3Cfont%20size%3D2px%3E%5Btest%5D%3C/font%3E%3C/a%3E%3Cspan%20style%3D%22float%3Aright%3Bmargin%3A2px%2010px%200px%200px%3B%22%3E%3Cimg%20src%3D%22includes/images/mini_user_disable.png%22%20id%3D%22icon_login_3%22%20/%3E%26nbsp%3B%3Cimg%20src%3D%22includes/images/mini_lock_enable.png%22%20id%3D%22iconpw_3%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27pw%27%2C3%29%22%20title%3D%22Copy%20password%22%20/%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_pw_in_list_3%22%20value%3D%22aiheobeo%22%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_login_in_list_3%22%20value%3D%22%22%3E%26nbsp%3B%3Cspan%20id%3D%22quick_icon_fav_3%22%20title%3D%22Manage%20Favorite%22%20class%3D%22cursor%20tip%22%3E%3Cimg%20src%3D%22includes/images/mini_star_disable.png%22%22%20onclick%3D%22ActionOnQuickIcon%283%2C1%29%22%20class%3D%22tip%22%20/%3E%3C/span%3E%3C/span%3E%3C/li%3EFpO8UDg4ODjv6xeijbE3Kb0PXaO ryZWeEDzoLFKMhlEdQayPUJq PumnYS8dVhZMdVm2sx4GbVAzgdb5yd7oBMy/ex0meZvtBoHBxvE/K2y6FIuUfMY61fPGafQMCLzID/iF7WVkrFvifRDHuAi0JPQHInY8Ikk45AVBiodS1jEUJrq5eqkX8AGD778jVI 3iPWZxKcTsX2L5QAvzHXiVDrG/k08LG3Ck3bW9NWHHQXWyzljNVPAT6CabdUqKTnKZNUePk0cCrqSm09nCnr2cl1bxWTkuQ188eWJQMstySwEHERcPfF7tJVzPoyKk8HGufobOqiTqFinZTCAprPCiGHSR8KLMZ4crUkCXk6cXJQTSbbg5BUQuZQqQFaIvJ9tLaYHNmDG2MV0mIblphhdWu78BFzAOTF4o61NxZYDaoJO1xEMk05zAE1ajuFsGO4sIxhv6q5Xm7QZnm4j/ZF36hOejoeH4lYDPifa/196n5 Pr8gNA61MNEKF7YnWQvC7CtedYnhVHGJxebJek1rNhPqZYJSM7i6djFBOnjUuoqGIwFwqLR4EwCayrYAhSCcRXl8RuBWdiI vz 6otx8S7sG3YxUvVJ1XuuVxkn8ljpj8bk5 AQiJQKQkQpI7qU4y/akKdjEiq3n23syelNMjO3kYi poaG3LxSuCiEUnIJ9vyDT3Ed140PE0XS7ePM18Fhr7maRcRViiT/4TtbH3/IeLRgI4/aq/AcDXQNtp83O0oqO0GBWGLIVrkgEpwikDQpRuV1pQ0NXFiYUL1TNODwORCqa98FhpYRUXDtwBNNgcwvu3ppFQBiEknw/b9Wrbqbur2hF8XyKouhrHQq2mZIF/EVolh49idF M2web1QjS 2bCE2hpO65t5vr56oFGOtnGwRElQg5qDk5 Z6q70IgZw4zYvfr5ML D06yWeRkw7JZD3Yy8VmR/Hz1RQyF4Vcu4ejA7UuuLE9pdmbsAVZdfExSwoVQGupdlqztSN55tvWahgZHEolsbx5S9afgMP6IOKtSPt3nnVNBH21aQbCzcQbN/3muj 0eNxJi4rbFgzbqQySQKUNgT0W8Dgc1Zz66d969Z8eP V8z3YPC4SSlSfU411WiPwpBuZMRbobPhLVy6FeydsspRslIRAtZgPadHm1V4/pSBZ6QArkZP EhKRQyql1D2ThdQ0tTnXjzJ7px4t/gAYHaoNA/mGomNYrx7yQMS5ukUvObhaNdRNCAKD3g31UZM6WffLotY0G84xZ9tuPHtaWPNBe  VRTOTrMwfeHxRebkSjvucZ7iXC0Q9dutD9xvdYvq5isEn1qEiU04M1oJFT6J3NEII9uJ8nvHbi4eb1aPirdMlBvlWZ2rcRHVCqPsUop6RLs9wUTf0K91IdhsUK o/MABadWZRDTFS9aDWZN6DwADRjHAyqFRL1XcMSl1dq5vk7IlUcyIu6dW4MotY5DGoaPAGdSmXOp8ZddvrvRqVI7d7L8q GSviTIFSr39yPPELmZ4BMgfVH 2Wd/Bpopp6PafR6BxGmpvo qtfdFgwou37Xi9moC6ZEkTtOWmJSrK0QZvzkN8MGGdgpd8L966hr2cwMte34W5DwZz36tugVv8pK0EzJV32nu T7K/IeutdbDG05QnAGokQbi5QddNfqNO2o8x4uK9ZuyRF1DbS2b6kaSZhhNUlyYZhfJWZFCKRabO26GNSalT9iUHUbDeLSOQxAnQk3Yv1ixiz8VGGFjSS9zEVFYW4W3mqnnQdp1OvZeZO6Dw/XZo2Zux6dMf/ymy/iIDDxh2hdYhCknVUNnkd9Fr7w9IgnVDwoCuHytSqi IIA2y5w alkhL2aBMy51OwSVAHcxkRRs5EbiMk3rC6IoEnRBCQVOPNmNlGhNw39Sj74 UNj/f/zMW9j0emB91XD8YkOISERMlmis3ooXeFvtH3rgV9A31kBNsK2pTksY6eEuuqcsvmt2 W4QMPyt85L 3WcbwFCyLsTDCoEYjHs 1ekQWKb1wN4COEQR1Vot4RavY7MGWnNduvHveEpOrQNDRyzrpnCKubHG7jRMQ8AIXrsCgpZwauZRdLwWR/XP91DtR6HktCIbGlKJR07gLK2hEhS/HWdenmY0VcMQ3C5BU3XFkhPDOi4HBPexNnMuOWFSNwuqa6WkH8tshfgdeBuuqEHoC4CIqPZAv0CgfaZeEHRWuyXAjqZK nrosY73iDuy3fEyRVHRNii3/361hRVna2OLgR2iTbQrEkRRXWpoIpb3bnZ6KRl cYaYbA/vWOlXnQ0R4lK4IcFM5tKAnZuDBZR7LvusvdvFRyote/Lm DYJQb5bYn7QGOWbI07AJarei/EvpWhj6gYrmcZ2m2LW EfpFOBvesv5Jn5TSY/CaHYKS7Oba03d2gklyfg5Nko7tS/OsdAOVaR qQ7oe2G7jL4EBr0X1FrPdMrp5t 403gGLt2OZ3Rl4Q6z3oYE9/TN8/rTiykMGZLFTQeJJwdhGXz /CzyBucXW1lzuIJmx9EO8TNsllXN6QLX0iZHWDmvRJQSMYB/KOsY73yb3 FkQB8aQUAmePci6nya6qQlOFdeVTavKmtv12siZyqQfPtIxYUQiVAgrD1cEfE11V0RnubfYQqw8nBOyZvdpmU5oLWll44ejyUJRN3OwbziSRrd8Bu6 bLzTbyxlvG5cIPp96p NjJDEJHd7o8cCFfBdxpdeQJ3SBz NRZHAsN9eJDTDkHPEYxSE51UatwG1rx499Fsv8HC833fixzxVH405G0MTrGt3r11RABTet NGcfiGVRsa hejq7bvi3Sy5Es02Q7MR3MBd2z4/SLuYj95ilROstc0A8kst9sZzu157UWez9Xiup9CGC GZ8HMyTpATXpZ817TY6jilMeYaIiLuJVAJFOx 0tubyQcd6WKTL MbRUdWaRuFnSrVHaPkcz5x3uAUAZ2rE1TNXxuJ8c51F/8tyEH3vDH0x1VEmRlldAmT8EuOSC/Fhx VeEzjA7sJ1UbDV6DEOGLpbiSRXJ5xIbQk JwOcuqg9BzQhAiqsUaCTMX1cPVM0InEZqBVSVviYMLmVnuzXsvs4vg57j tzHQR5cXaCrBjIJ2Qkv/cD3wZVB5Wmg4tJu89/7 Ye4XAyHJjqczWqwFSLy/eXB","2","");
INSERT INTO teampass_log_system VALUES("10","error","1354535825","%3Cli%20name%3D%22it1%22%20ondblclick%3D%22AfficherDetailsItem%28%271%27%2C%270%27%2C%270%27%2C%20%27%27%2C%20%27%27%2C%20true%29%22%20class%3D%22item_draggable%22%20id%3D%221%22%20style%3D%22margin-left%3A-30px%3B%22%3E%3Cimg%20src%3D%22includes/images/grippy.png%22%20style%3D%22margin-right%3A5px%3Bcursor%3Ahand%3B%22%20alt%3D%22%22%20class%3D%22grippy%22%20%20/%3E%3Cimg%20src%3D%22includes/images/tag-small-green.png%22%3E%26nbsp%3B%3Ca%20id%3D%22fileclass1%22%20class%3D%22file%22%20onclick%3D%22AfficherDetailsItem%28%271%27%2C%270%27%2C%270%27%2C%20%27%27%2C%27%27%2C%27%27%29%22%3Eit1%26nbsp%3B%3Cfont%20size%3D2px%3E%5Bcuicui%5D%3C/font%3E%3C/a%3E%3Cspan%20style%3D%22float%3Aright%3Bmargin%3A2px%2010px%200px%200px%3B%22%3E%3Cimg%20src%3D%22includes/images/mini_user_enable.png%22%20id%3D%22iconlogin_1%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27login%27%2C1%29%22%20title%3D%22Copy%20login%22%20/%3E%26nbsp%3B%3Cimg%20src%3D%22includes/images/mini_lock_enable.png%22%20id%3D%22iconpw_1%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27pw%27%2C1%29%22%20title%3D%22Copy%20password%22%20/%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_pw_in_list_1%22%20value%3D%22tooquohx%22%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_login_in_list_1%22%20value%3D%22Moi%22%3E%26nbsp%3B%3Cspan%20id%3D%22quick_icon_fav_1%22%20title%3D%22Manage%20Favorite%22%20class%3D%22cursor%20tip%22%3E%3Cimg%20src%3D%22includes/images/mini_star_disable.png%22%22%20onclick%3D%22ActionOnQuickIcon%281%2C1%29%22%20class%3D%22tip%22%20/%3E%3C/span%3E%3C/span%3E%3C/li%3E%3Cli%20name%3D%22it2%22%20ondblclick%3D%22AfficherDetailsItem%28%272%27%2C%270%27%2C%270%27%2C%20%27%27%2C%20%27%27%2C%20true%29%22%20class%3D%22item_draggable%22%20id%3D%222%22%20style%3D%22margin-left%3A-30px%3B%22%3E%3Cimg%20src%3D%22includes/images/grippy.png%22%20style%3D%22margin-right%3A5px%3Bcursor%3Ahand%3B%22%20alt%3D%22%22%20class%3D%22grippy%22%20%20/%3E%3Cimg%20src%3D%22includes/images/tag-small-green.png%22%3E%26nbsp%3B%3Ca%20id%3D%22fileclass2%22%20class%3D%22file%22%20onclick%3D%22AfficherDetailsItem%28%272%27%2C%270%27%2C%270%27%2C%20%27%27%2C%27%27%2C%27%27%29%22%3Eit2%3C/a%3E%3Cspan%20style%3D%22float%3Aright%3Bmargin%3A2px%2010px%200px%200px%3B%22%3E%3Cimg%20src%3D%22includes/images/mini_user_disable.png%22%20id%3D%22icon_login_2%22%20/%3E%26nbsp%3B%3Cimg%20src%3D%22includes/images/mini_lock_enable.png%22%20id%3D%22iconpw_2%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27pw%27%2C2%29%22%20title%3D%22Copy%20password%22%20/%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_pw_in_list_2%22%20value%3D%22xxXURfc%239%22%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_login_in_list_2%22%20value%3D%22%22%3E%26nbsp%3B%3Cspan%20id%3D%22quick_icon_fav_2%22%20title%3D%22Manage%20Favorite%22%20class%3D%22cursor%20tip%22%3E%3Cimg%20src%3D%22includes/images/mini_star_disable.png%22%22%20onclick%3D%22ActionOnQuickIcon%282%2C1%29%22%20class%3D%22tip%22%20/%3E%3C/span%3E%3C/span%3E%3C/li%3EkJO8UEhISEgqqfWUvx mGtpP1q3PJW59jsy9aq /PjsghAmBjKoAkRhR1e4G3hKUOCEy22ySvlyC0Ei65 WxQLS3d/3x29DQSS86l4bjDB9yXtrmIEC17kftkc76jkvJc6umKxls4m90xr65ZAVgQ2kq8etn n3ddC19AGC ZkQji2scG9o3nHdqedlsjy26vvZT7pEmETXofzN9C/s4 vMtBplZGQHqIKMEAqbwCbx2IS1hCaVJBGLNTLMSYZ76UEGs13wm7AsIHbmlmMu5QYDM7Jw6wvfq28OCU09 Kr3pe3OJJDHyq/7YLSeJjwb3WKZQ9cCpxpkUfkEgV6uePTSwq4aLpwXo3/d5Y9SITRulbNuOh1KI1RHcxN3q1hsEKq6/Ab24F8oE5tf0krdToQSFH7wO52S6Ur05jRJElInw5rhzWPjGJJKx3wjRfQy9U2EykAQa9m5uylMIzQRK3wevMMMhecp5DUR76Hf2UwD10TXk GJh9sYujaHQuiV0SJOGKnMaXz71gjhuQfOhxL8rKksd0s2z0nkpQGt6  E8Moap/Xvvp3rducWu1TOC9WoOUvgYdQvM293jKz90yHOG9Ph1K2k m7IjUIshRIcLLWQ4b1/T8Uudpzs2UwbI2BglC8Up /aFVOWyAzRkSMRmPQ/D3FtVjR3MMNqXDJ8Jh2JT2diQeibPvUMV2f1aZ67 pQfhUXARUD78Nfupu3SIEX0r3HY8IAgsHRGLDVF3qU1GcLx0WfPiWBCVhYTRGapo0vsGFaphT6chu9kvDItt9Uu9MOzz/zjo4bV4duRLk7qOQFpcZiQt3XdHo9aOBCvbowXMLNR3WCSdW8Ggy2o5/bgFxq1pSOGFpc1h4T2DRJCUoBOfLtKETDUCbAmbWOzo/1IpwmbFuE7rlJjygnagP9TC0Z3Zjf4S0WIkm6ocb6Y/FA3R2ogSJj/JDag2fa16z3IcrgMXBypoSJYxvLs6nk9hSppxuTrSe7C3xvquR1FRjRuiqeTmo/faevdurW9DVRsTX/asePmK7ZuP7JprUnLtYarczmMp9IA 8WvR51 9S5Q4SQAIUFuIZL30yAyeN LYD04hkiP8/NhuEwiKPz7KHFAuNCpSg/B0pX0idnwkReFh9uQIPhIIHqDmSf9svyALP9i2t9j8Cg5LVTHCvdwvxe9tWMDOLmfccfsAR PgfKVY2HjwnaQ4Fo1gggDgytYDjwHqg/qHWHO3RLfci7qFEuwZ9RxVo753jPL9QDK0D9VGNGOsb2DIvodzy 1zLgc7hJ5eJTIU0Ex8FXbRafOfFlnR0zj4CjRvtg8ovnB3xNtOmIN3P Br 3Qqq6oxEc5QW9D7tKgPMvbSoVJ23UMFZHspO1nI0uerRGswd3qZmOeeb2i1PD0MLK2nzcSXUqxf5LfZGhg4ScWBIKnYM31SH/focAwLcIPgDOfs/Qf9bivknVDCrytOKzbAq7TbOa7d2Qqe1QpNY/6XYCjp76Kc/nOnjxyW87ZgbUvUvoemoNI13PCoTSRJQg2aghh1cC6HP8qa8UgS01ISAC/fw73FU9G7fBaoRp9z045eLNBXHUBRlPc4jT85yJJdOj6qvbnWzt0PwDcS //dmARf1FpRzwNhEw8/td1Lslr16FhrDjfmrXMhE6wXYb3gC3II1Xs5FknmRZ034FDmdBsgw2zOgPwud9XgfoEI8 9NTEY9hEedfKm4QTwexc2GsnM0CFb8gxBBrQ2tlvId53s37g4XbMrcyI5a B4uRBC1IC0M6nL2aLjsL3JFdgamUOr8rVxy7xW/dMhl1yeuNTYCusbhtpVH6993Qmdp4W4FEOlXrPAAa7jIALw Z2c9b6OEW6UmdKMUxUVPHcCz 3s6hgzUqG1JjQJiEK0PCKTdPzoBpUSriN9p Tww2C88EAxwygh3dYdF eq4I6etIhNas41J7Q1TAVg6 kr2VfcWaMwCAqkqT9mbaGoNGSLHoP32rkxmUKM8GnBSB0LE7jXnjllGDGEddFAfhAqdkIUdL9ipUcRc49x4mfpFyf6xuj8x47ZFQRzMefmKZgIav75 y/RyW7qQ Ga7CnB/pP3NLHDZtBnMdkW5dLdGI oC8bK7y31QHrsUFUWiQ72Piuh pW6JGafRz664StN24yyWJZPp7BhaOurOX7UhQmUcpHgPwHGnvrj1 nUnfbtytBfHgn02Vww2FQh9tAzEMOaAF85iRDlKPm5rfG4LeVzTVIO2slJEQCRJsPmeESCDfXAXgwmSFXdKzPtCUV L5h9XMXD2atUN r5mor2M0WJsD/Dr5aAFSxSx76rEXok7M0Lxs5xcn9r8QVR3A9RDh7n pLvqpFuYnoCVPhP8eScaoqcRoEQQ/51a0M/RhZpVN2318aWZ8oVuAIQMSdj23h 8try sw9MBc0AYC0o/Vx8lxsYOjfmediF8zo9TO3ZBkbJikZCKQ/N4GB7zWyVcyMZvoHYT4ZjWWqROghoNd4Z1oSYU8z5L1cWmqeeUklhFmGQLxQwRfgZ33CwcsnNpAJK7k2G6reKUMFTAjXxhoAK18Y6Jgv 8Qv/fCTSh fRqvjhxbqo1h6 piWnVueYlpFL3byLHTIxpzQlioI6LpLVvQSKrYNrvBjLL4QhVBZj9zVVs/3l9iXz4JTd58Hcfw F 2ukzivjsWpYnUlU9wgq4Z39CclkD3P5K8IcPXzmJb96bij8lULbhsedgLITqAFwBO1NtAekcVkozPi2m us rxreOpBBlesZq82iHd3kqThU6c27NkO JcfXNq4Puo6/loMP6NfraRRSPJcqZsaj5LU UAmL Wfw65sElXoJ1doVcXp/uXgHMMaQWQfO49DhPc1rVOD7rpQ4Tx9zrz5h/nLy5pcOYIWt624jRo3Q 68Vn0lfz5irBlfNG7xr5CGHqM AbbuFurMrYK COkQYKIJ2JnhxpmtjCn0FEUyQqXsJSpN2jCzNu1ZryHvMnmiT75SFF4SO6uKxbRDlMV4YGOgBbLN742OyLWua4OEcFdjb6e7fANk9R9XiF78U6498kVWGM2wn0jh8fZh bb8z6D6E3dvH l5xQwzXzdRbUtNoDDHN/fnkToiXs73Werp3VCppKtIwYNqmBoHFg6utnIGl6DfXMfdz8Ev5IsD/KmXpRKi9POFHuIjItRG640vj0TWqPdDuL/cwVRRtR8DNbWKKBIwzkV wSBoul0oz4 bsu1xiYp8VHWS6j5SCtDaD9 TkB5oy6UxVJqL eM1jBvaV1AolNyRkvwa e9819FziXREHx34GNQPpjnGfcE2k shjZu3a8KER4ow54K7eXACw  lHnftDsNmG6KCTMI6BGD/PgnkIZJFtoKjECAM6k4S5ilnfVbqS9Xj3JgjousTMCalBQHHT8BYg/AVKH3fLxjaU vCzeyLwAIgDABK5MobH1/QSkb1o1r/Vp4BGICI U3Pw96zUCpgfV43P5vscTsdVZkPpbPAwjGlH2ymiLO5zgl2FSBaiFDfzmmtiP8Ipt0jcY3KW8LEjgfDMPIEVXinX1Iq2WrwwRJK3l4jBUZBz6MWjogVIDRVu0vrYZLAMw3Kfmr30pVorADkGxt8ItW27nxltPlYd/rWHA8fXyufjWNd3tLU9WQstBjGifqYTkdudM1eJPhpEI9Ja2ni9E5FGbcwAT0HQDeG1MEJXyZpOWZGCntTBydTvc9wgwaZtGEeLwYoJEiq5Y8D962pWRputuCGs3jv1GEGUwFnIuEHqMsoMNEzFopl4rEGMryUTSkeMAOvkLGaTBmdaBNk4 yJ936WMpqPYPrg1H DguSsaQkPFVOlBt2nIT/Gqp018 jY7D2cmsYw 8k3YY1civGkJt5VNMn/7K3tB6qim/tbBSmnttCBZHscuQmcwVzxsgCWxVPIxLJsHHvOyh0bqWWy/kG4NABz8q7Q4vS7wWyBLh/3fNsG6iEWlv1HwZIi3uqo9w6ondPf9L4j2jouIc0enSykzPZXppYwlurM0Mc2I4DCjQbtpxCk57ZDin2TfAODUmuwVslCyBDQmLvzdJSB0BceZhNt0ZLuRqJiBMsDtuC/8HNSd26vz625zV4mlCjXdBUlKPxlvB3qmzU/TXqut/5tZPCFP7obNHBz7IUwHlU0i/pGFB5t pR4 XQkKm63W2054RGvWmp8kaoS0rcfU8hbCwRdeO/6I28TzN8e95P2Rh2/ePccsvsR RecUQaHCLEH8tYwgrz/evuUrPGnHcLknjXYxRHUtctJkstWtvTwUK/6JJU3icZtwzu2I1gniHKo4WPyBAv2Dz5cdkVl9C339mq JhTAa6nZ5L /mE8pgm3uoa24O8VRLZ5bl0iGcShdFCUh5ARPbtkS03KayWC5xhzR3nfMxccRmXuB0N9kHWLT6SHISLwv2SXm8xDt2XdUP56bufbttR8H/8JQf/S92pTM/Xm7BIJHamykMM7wWbjffP DL8cyKiEfUU1qopTV0or9ULfI361BoE3pDy3UiWr6Cl6dPPDLElgHi6MIBj3d8ou1ZlTFfRMU6I/4yvythLT9LLzcKjyIMMT8NXvMVhQzvDKF3q0BAkcj Ov32z7acbFsCY975Dax8/C579lNd KB2H69QvSB3RsUORwDeFQMs49cGpppCAay8lJHsI06HIJynHVuXsymhvEix01ymHrSdHC21qDk9TdFcQds/rzR8vITShiUYDw2LD5JXw/NpPbkaKSAuQcq7sGzepL Rj/XyZEsLQu2EuDqURncEPJIQEZ7Dj0bYzTr27K PdKeW37DCespX1tIaVc9KMV2rqRwkyfRAibkG NcrY0QasjNiV IEMTD7JOT1f6GUANriBfodkrv2Tge6mwB4SI9qxKufnhmx7xyk1zYIZ82aDbVIxgJVOBRwj49UTqyRYdTKEvGD10oGItcg1pB7GKCRc TH1gbR8yu FBHXXFUtkPueAgIFS3lXHOKudqtHqBpX4h55YxrpHX/ iO2gzBcKVsl 6CJRv1K89EmQJSp4afHxncaj72L0 /MWriz/Jz25KPeq Ss4iGk16mO1aLaCO7QYpt0FtCC3gH5zp0ICEyohKo Y64Uej3O9F97ugxUWo0nycJKOki7 LIvrpv7dr tQFbRtDvGuA7hfjLb40Z0wXBNUD2cEqNrFY0aiJZo8ZhY26pdj1CxLvH3VF9Tx53/bY/j6kSs5c8YaqRVwGTEZ97y7FFcnXX1RXJV/mNmqM9l3WaGhkbXrfAdqMqc7N4tZkrhLNj8ddgxuQy6PJe5F4NGaejuGF00","2","");
INSERT INTO teampass_log_system VALUES("11","error","1354536551","Zpa8UOLi4uJma8chkZog2MzJwkNkBhwMMvbURnFfInbG5ODw0OC fp26XxHFJw9GKBTafTfN0i/kayVGBaj/wEsA/9n1arILV6d6NEp96FjCGXbzsintoEd6jYm1wGxPFwHOvS0BhFVZS3oHhfCWBLg uXAEdf2ksQ4rhnIQWIwLxH71xdPklgNqEwtQ/riFaKssh2gbp/RuxUgvta2fPASzyICic F4Snxt4rmUxfpHRZgRDD 1l4CH79V dOFO4 izbzurAstszEdAnnvC4alA9VxYAZWaA4nEOjFLIkBlqdebkeEWP7hsU4uTQ4gpIsEGVdNXmSgTiAeQ8h459kdUt9WuQisZvfdnBV0yLXKMEmOgqFGOJoARfYtEn qO/TuM6JyfU31jl82huXcVVLDfrsfaUIfMVuRdDcgvTrfof4gysXJgjDxC2I69Z/Y/3dKPgPi5EpOOCk CMRm3Dvq /Ww4o3p6gO4Zj7B5spqqPQZZ1UhR2tVhCjxEqdoTbIv39kAcHBGio1NrN/VXgpP6syuvstMWNQ4YI jNjB3qYCC/dL3rY4khdb8dayM7zj98eRDUkVhznSzS8uqt5VzvWnXEwIMwWSt SusQVPYRmWO6hgRx zhTNDsEzzqxssSnPetbqjRfB6wYIiDbugSx6rQvb9oM4XWOTuevE1WcYZNNXfYmILu1ZspCtv/GmvubMJWYk86og7a3xsrM5crcUmX15UwCRkhNUKn5Vl27UIYRHmj8q5vol3LyGDul9Za5QRQhVU4nSl0mtg5Rc6rtU4vRjeGWKZWTduvCG53VXUEuPG1MGn5oKy9OEZyvO/A8ifvavTncch45VDVHA/NCKoqnPVKQHhRg gmgXwS61oOrZAHO4rTP8XoZxfuLbRdBDCPU5VWNoswEpsbQAWfLFbySNdUrQZRTxfq7Te tn/7GNN4BPmhoO/YOCcvI r053N0 DQpptEis84lyq62HUWuorJZIdE4XpaUQotvxdxp5MXfXxCTR5S1iXoIydOQGtn0YFs6PoJsFrRqNSyEt/StK1Wmm6CvSQtW5JGDJ9RPktns5vpw6 9S7gzRsjGFWNSXgQvX6sTB5bX7Kd82k6JS JzPEbNw6xHeCfaPMec/xehgYt2D 860ESDEqi0jITJLpoU6TgB8ZeYCAfD9CfRTXGkva4tbAQng7h9dcrgUsuj2jBKvgqbtqx5M57YS/8WA9vZgLZwb3CoG byVFTJ6Cw3ULzG82LezWY9/918bkOiiUxXDLorM5ILT3PFRx5xt1sSTPtutB/KmiKNGOqemaaWHkZB iZ28t/28v3eA/BLpFTX CduAEe0mnjJwIqlxEEh8tuwknAkQNh14y2m6IJNmnk izsxLNE4JQIaES2FKsFWPTDSQNTMsaPl/J8DX6Sui0UJI8NslgjYSkBrDzJ45mGVgfEDDheIhISqXEll0lzTKLILXOAPZ/jdXe2Rmojr3OaYPFQH9GLy6Q1DakTGAeTgTT QpXnuQApFHBoJ2A1G5yoXn8 O/YAG2PXWR94qSPuxSOOUE/rhZ8yiI/WFcLZDik1QS0Oja 8MP vu4p0VqOuDDyEV5w6eaUSotYcmriUAntC6RuPkhvJRSO2 XIkTlYVzC cMTMweyKvh9iBHvypjcOXSOr/bYvmFjGI9nuBs28w85 deTEsAIq7sDyXM/hNu7t2Z0eYGCcWUJ8EoAe6r67vk m3520SlrDU8ZvHLz5PQjjLZtQDfEi76PGR0lr8s2zTMHx1/ Oi8DUxTRgNqhVVr1XaXgoFpj4xSuGYeoEGchsP PsFEohpgrJKHaHd39 BonfA/PbXWElnxfKfM4842Ek7R2/7G2OdzSdgGog7Yay9RCJ1 34YoPFhu0liavhfRKnNPo4jMzYxRC4Am7eMEVK6skSF50OdLGG5DqyYH4JJIsH9OGls7JTZLlsBeUQG7X2FYcauJ09RalHWB2/G5KCqPqLkzUxsZK3SeVDQm8xtRreiXac0itodD1iKjiXSMFx4NFA6qKoVyKIs13AJj/VX5ZDApaTh9IGkK6eB9VizNs3UVOcGo9yxeEv6z n3U4w4EMq3trzx5cngnw28oz1JL7bDIF33VEYmtnnsf4S unFyZrdWvRNFtqFM8FRBzypW3FCrKkhTtyvhnKFPT9xxIgEYBw65e/s/xZc2GhlG30R2OTVjl H6zmNgSGilrw/1kfeWTrWvXLe5rY8okufFhaPU5ipvhWPgW RDnLKkLL7YC VQ/SV5z4uR/jVrjI14cuaiPk9i 9F kJJ07KA77Gesh0UCWBzxKC3g72DS2 w81GYFP4qJfBkCm6I/n1wfBYlSGMXhl3pLxh6793gDb8VbEpyoqZZHAuV/HKvsVgNZIWmHbmlQVX1i tK/xKHB2QSY7/4lq1MdvlyjmFVkD3KEo/0SZ Wb9JAApU/zoA1O42MvSsNlX0O4kYZDLcAY6XhkEYShpbvESgzm28LEfeMzRdt7jLIZAe1Eb8tasveyhWspA75oOIEKRTpdJ04CFKPIoyQKyPlPBYv9vVCmh9YwIT6J05TMGkOBBy8cRiWhGkHs fXhOBC1xKJome6q75fstl/rOeulYuF6U7aT3orxd/3WWuCkbx2JjTwAx1SZ1WUQ6Z CR81WryeHgW0LqOhEWJ1pdEiLnoOhBsYjfntqFpMSPIixggdbyBS1gaSRhNJ2HS6P/1JAKf 6slXyHkVcQIDlna/Pudb oZn3X2K7s3dnk1BSrClrrsoyxBESMVh8/CtX loxGokUrHUPItcZpnSBBUWfAsjGIxSKh96ao9hD1CwFfMS4ZqoN5IAVusMAZWI lKUG/1qklcz91z56VUiQz8ys0zXHeHivWhWZGTuwz04N9DNa0 lyKFQMNs BWEr3C 32qL2ehA3lCDtLT0N/Ic1fCAps18rlWRRvFtoUhnx09Mn0a3qwBJyc38bXMrbRUBhbCR1ZBIPv48l1tnh Wa9JCzYjuIbNcyKZVfsXrcnm7Xv5CXNDLOp35GhM ivXBm9B7QHliSMlqjj4aoUT1Jro51DUGZ5jLXcgd4bNLGAiHlWl0NsEc88klMXy2gNxApod9S5h0493DykhFLtfZO4e/O flTBYg7pA2dyjgW5 mEGj7pQXYHLqD63lCUcPJCZ%3Cli%20name%3D%22it3%22%20ondblclick%3D%22AfficherDetailsItem%28%273%27%2C%270%27%2C%270%27%2C%20%27%27%2C%20%27%27%2C%20true%29%22%20class%3D%22item_draggable%22%20id%3D%223%22%20style%3D%22margin-left%3A-30px%3B%22%3E%3Cimg%20src%3D%22includes/images/grippy.png%22%20style%3D%22margin-right%3A5px%3Bcursor%3Ahand%3B%22%20alt%3D%22%22%20class%3D%22grippy%22%20%20/%3E%3Cimg%20src%3D%22includes/images/tag-small-green.png%22%3E%26nbsp%3B%3Ca%20id%3D%22fileclass3%22%20class%3D%22file%22%20onclick%3D%22AfficherDetailsItem%28%273%27%2C%270%27%2C%270%27%2C%20%27%27%2C%27%27%2C%27%27%29%22%3Eit3%26nbsp%3B%3Cfont%20size%3D%222px%22%3E%5Btest%5D%3C/font%3E%3C/a%3E%3Cspan%20style%3D%22float%3Aright%3Bmargin%3A2px%2010px%200px%200px%3B%22%3E%3Cimg%20src%3D%22includes/images/mini_user_disable.png%22%20id%3D%22icon_login_3%22%20/%3E%26nbsp%3B%3Cimg%20src%3D%22includes/images/mini_lock_enable.png%22%20id%3D%22iconpw_3%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27pw%27%2C3%29%22%20title%3D%22Copy%20password%22%20/%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_pw_in_list_3%22%20value%3D%22aiheobeo%22%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_login_in_list_3%22%20value%3D%22%22%3E%26nbsp%3B%3Cspan%20id%3D%22quick_icon_fav_3%22%20title%3D%22Manage%20Favorite%22%20class%3D%22cursor%20tip%22%3E%3Cimg%20src%3D%22includes/images/mini_star_disable.png%22%22%20onclick%3D%22ActionOnQuickIcon%283%2C1%29%22%20class%3D%22tip%22%20/%3E%3C/span%3E%3C/span%3E%3C/li%3E","2","");
INSERT INTO teampass_log_system VALUES("12","error","1354536565","dJa8UKCgoKCCbD2WIetVsvto461 pSRzUq04uV9MSi1k4lf8ZXNedobz6WFN/zqwIwo2U9aBPpaWL/ tqJSkpBJwYOHAg4jadOQz71wYfosIPk3xNVbVUeZCPPKJqrWBp2r8CycJWQ/gnii6IR4EdNvIw5KAqgu/ as4KqE /BP  nsn5naAkWW54mTAIgDP9CWqm3aCRckVwUJDzft1Jl6BhD1FYKDrPODC5eEpvIDaj00bokkGaDKVIbsAD3QR8R/fy bwTa8oWrzTJ M4OaEBYgyTamKFLb47VBNQgxwPfo4XShdEG2XIrMYz2XLOCli5WSGXVCDGOYnjiJva6mE8wmDl8uKeoGjLly/z2VEcpunYhpNNWTzXBm7KGCoeHmwaBpbf5zZ32/ABiJtkSVjdTwnijFEkwBOEPw9FDMbNf0ih6eoTAu7Osa8MJ8w9eCJyz3jhi k9BM 4Mi9IMhaksjOtSPohw8PjJrp7zavI07B5CCeJLO70f/3IIP5b4nkgYBNc9eSNFBgP4 f1Ivk5OY9XH1 CxNTv/fmCMmcUrG ygskQMdIT3paoxPuDhG/zgnqmC53gKyI8E3xgEHAnsWcbM9dlQnBYh 5h2L7EOcdltZ5uQkYDrdSUEomvSmYYDcUzrXZG kE05cCYPe0 7ZOg4GSghplF6dkyL75HUk9LikY3Ohtam84CpFwpGru6pchZZqIuq pCO1K6vj2aaNAwdjzmohEIwOBbrYwS0/yvNNz1gSOsUj64ri9d9OKRJ5vWoaA1wo1KPqx7c5uMS1mcxvxqbv5IzVQShfj93W566s253BgGiJmv7rmTuemOwflbyZrSFwfcWBaIlngjomNIwLvAgrk7tuES09A1Yz1GSC8pkGkyFlnhBjX0FXV0JjQ0KJr9 UryPMpAPi4aFgyggUgivN1R57xpk/PNL2LUaZoQxlbDtn2iyrzu55Gf6HqWTb1BgxXLo8cu6K5GHAZWkYV/FZoo73l1jaLEJtQWC5PjG6qN5vVyhTIhlc3pGvXYUSmUdMkhECbrBInVDa707MeZsT1ue7swAiTJldwGDaLAL5KwG7H69CYB97gY8VDLlMjKPslsbY7wwLO Lq4adP1Wt/6HMOyxlWdkwF3TI03PbxXu6jL z8BgTf4MOLP8lmqdC0oKr6NjvsfUTmdbL4c0BiD1eeTlx2VGGTrMvdLmwJgLbM3d dZFM 6mEI3iN2P KeieS5PkvCa0FitAmNaZ8n89aM45InPdhWhE/eZzPqHUNORDGykEIetbfO2EqWI9OQq1C4KEKoNi6Vi8yL2/i0vnHoHQRwiLZhIOVbfxOm AUzjR6YhEJgLhpYc8p drDBZbzts2KE1jXZz EZlFBui3ikD7Z7uLVM1Dx2njONpuJT15X9mIhyumMA1vXN7JMoXXw0O3VZiONhJYmMYFooP15L5IDfR5zhjYUmWJupRVYUTVBJwjATiCkhrlf/77xoEqABK31mtYoaB5GTGfUpXU79AqTpnWVlXi1xpoKgXVsZnKbknOAus/o/u/ZPyaOE9Upbi/iwcK4xVvZFZuECrc9BrmDiSJcmIoGs4d2yyjh72sOqus9ynZCPa9JBSok e8IjWdmFBTgAxTUHcFGu03ZdWGreYUfQobPdkDMHTE0WhdPhWbzKcDE1Gvmv7wDlon/VeuHxSfdT2rXmgoJCKTCu6vwuKyRoh TI6rxrNPr/iP x4HHxaq0MMWYBY9 ms6yTUhb38Ou9kP3iIUfRle67MtcuccFtk0eyC2nPLG23qEhTO0oIHNzqxKw0ULE9mwYvj5Xyjhj8RERjIr9CnXrSDM7Tgz6aSAjI9/JisR9R9qITwEtnZUdMrmsdLY2dkCQcASAwanwrcgqfjIN8wc5wH3Ovl9rACg/wwcILrJBu1Ly7jsibw wH7xLpTBaObeV9zcyydp6WVfG73FL7ZZXvZvSTVkOIOgj7AapDNQKUpCVTSxMYSFQSvsy1qBPjvyRykj6CD3TRQUogurj2AUWr66Qv6aJ5eCwFv/FHG4S22qDS4dA4BCmrXCQqfj8jsz/Jr6KCOyePHXiCAmx1pZvXmW1iFg7/zMf51z6krBCuIIz9DSXzFUcfHdwPUgcYznSlG3t2gita1B z1mf27WlIpUhDRxRcjWkQNjnYV5teIFwIuYSGgNBW0nouM8HTr6W2mUt/z5HZb2kCe8w6uhSQIrIB8R2bTcFXBRmZyVOkkp1OqjY9fiBqePJDXT8LZUsV1WGuFs5f2JCni0GvDCZF j4oISWttcZ2LBWo5Upm56HgjWLjiEFILBgGImmy ejVIPh7f4nhZH0SDy9ja4MFiE7S3hX9UqGyxr8u5C16RcQr8JFoYXswFCzuqj7z75PYl018UdOVMu lX8xzOcj3oeBnQ/yyN3OEqJUNiYz5lVIR76mrekxIWeOtr4nB2v iyWuTpC1abwzKGHqAzv hyNDWYH2tVe baYScaqd2Ds/yyEbm3uuEPW54pr1Vr1SsUnKlWRqVrWlT5mQ6WSfdTE3L u2 H3qrkoHv/ohOz H0G5evPrHWPXTTgtlGKCE4KW0K rLSfBIKUOTEc/3yrvhl KXsyO2QmuHCPijLSyoVS1sW5nTXF2m31kzq6cjT2R7TXGFdh2XiZZsA79RWNbH0J9BCSk Fvm8T5eBV2fhDPzbFSMD3G8md0YRQXgIdKGG1tkWbiIWfQJNw5hAnIUe iyedUqIuhA6BnzjtmXQsbRfv/UXdlnOWiLfFY/2dyktaSyp4c1HcninIYyhpK57Ks uAcxt27gq4AE86GfT1VwdyAIoQga9vcrBWeivRrMk2T7vY6hc0IGoN YnKo4fovobDWu7pcEsvCWHfOWneDWAvM/6yt0Zz8EjoskS0pAeYFi7ygpYKBOtSf YecKydGKbT3zVdkvkyLQ0Em OCNFVk3xZohGw5SLhOPBMjjvv6k/sFAAxB/vYi5NC7BgWLSlel01ZLPXESW6CQNfhEM0MuOjQbvVCouG 5RHcDjjqKQLbQ3U4TOLR5h/KPSW0I5rvlWBeR0OSwtuPaRSdpjefgGw6sXIRnd 9k0IYYqsWyj78TbDfcArTka5Hzj59W6QP8Wr1gnKg/NevXEM1gNW 7I VQ1Jkbi4F Grptp8QhPG/pyZUpRTL4OK%3Cli%20name%3D%22it3%22%20ondblclick%3D%22AfficherDetailsItem%28%273%27%2C%270%27%2C%270%27%2C%20%27%27%2C%20%27%27%2C%20true%29%22%20class%3D%22item_draggable%22%20id%3D%223%22%20style%3D%22margin-left%3A-30px%3B%22%3E%3Cimg%20src%3D%22includes/images/grippy.png%22%20style%3D%22margin-right%3A5px%3Bcursor%3Ahand%3B%22%20alt%3D%22%22%20class%3D%22grippy%22%20%20/%3E%3Cimg%20src%3D%22includes/images/tag-small-green.png%22%3E%26nbsp%3B%3Ca%20id%3D%22fileclass3%22%20class%3D%22file%22%20onclick%3D%22AfficherDetailsItem%28%273%27%2C%270%27%2C%270%27%2C%20%27%27%2C%27%27%2C%27%27%29%22%3Eit3%26nbsp%3B%3Cfont%20size%3D%222px%22%3E%5Btest%5D%3C/font%3E%3C/a%3E%3Cspan%20style%3D%22float%3Aright%3Bmargin%3A2px%2010px%200px%200px%3B%22%3E%3Cimg%20src%3D%22includes/images/mini_user_disable.png%22%20id%3D%22icon_login_3%22%20/%3E%26nbsp%3B%3Cimg%20src%3D%22includes/images/mini_lock_enable.png%22%20id%3D%22iconpw_3%22%20class%3D%22copy_clipboard%20tip%22%20onclick%3D%22get_clipboard_item%28%27pw%27%2C3%29%22%20title%3D%22Copy%20password%22%20/%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_pw_in_list_3%22%20value%3D%22aiheobeo%22%3E%3Cinput%20type%3D%22hidden%22%20id%3D%22item_login_in_list_3%22%20value%3D%22%22%3E%26nbsp%3B%3Cspan%20id%3D%22quick_icon_fav_3%22%20title%3D%22Manage%20Favorite%22%20class%3D%22cursor%20tip%22%3E%3Cimg%20src%3D%22includes/images/mini_star_disable.png%22%22%20onclick%3D%22ActionOnQuickIcon%283%2C1%29%22%20class%3D%22tip%22%20/%3E%3C/span%3E%3C/span%3E%3C/li%3E","2","");
INSERT INTO teampass_log_system VALUES("13","user_mngt","1354537397","at_user_added","1","4");
INSERT INTO teampass_log_system VALUES("14","user_mngt","1354537423","at_user_initial_pwd_changed","4","4");
INSERT INTO teampass_log_system VALUES("15","user_mngt","1354537425","at_user_initial_pwd_changed","4","4");
INSERT INTO teampass_log_system VALUES("16","user_mngt","1354537862","at_user_added","1","5");
INSERT INTO teampass_log_system VALUES("17","user_mngt","1354537905","at_user_initial_pwd_changed","5","5");
INSERT INTO teampass_log_system VALUES("18","user_mngt","1354539191","at_user_added","5","6");



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
INSERT INTO teampass_misc VALUES("admin","log_connections","0");
INSERT INTO teampass_misc VALUES("admin","log_accessed","1");
INSERT INTO teampass_misc VALUES("admin","time_format","H:i:s");
INSERT INTO teampass_misc VALUES("admin","date_format","d/m/Y");
INSERT INTO teampass_misc VALUES("admin","duplicate_folder","0");
INSERT INTO teampass_misc VALUES("admin","duplicate_item","0");
INSERT INTO teampass_misc VALUES("admin","number_of_used_pw","3");
INSERT INTO teampass_misc VALUES("admin","manager_edit","1");
INSERT INTO teampass_misc VALUES("admin","cpassman_dir","C:/nils.laumaille/utils/xampp/htdocs/teampass");
INSERT INTO teampass_misc VALUES("admin","cpassman_url","http://localhost/teampass");
INSERT INTO teampass_misc VALUES("admin","favicon","http://localhost/teampass/favico.ico");
INSERT INTO teampass_misc VALUES("admin","path_to_upload_folder","C:/nils.laumaille/utils/xampp/htdocs/teampass/upload");
INSERT INTO teampass_misc VALUES("admin","url_to_upload_folder","http://localhost/teampass/upload");
INSERT INTO teampass_misc VALUES("admin","path_to_files_folder","C:/nils.laumaille/utils/xampp/htdocs/teampass/files");
INSERT INTO teampass_misc VALUES("admin","url_to_files_folder","http://localhost/teampass/files");
INSERT INTO teampass_misc VALUES("admin","activate_expiration","0");
INSERT INTO teampass_misc VALUES("admin","pw_life_duration","0");
INSERT INTO teampass_misc VALUES("admin","maintenance_mode","0");
INSERT INTO teampass_misc VALUES("admin","cpassman_version","2.1.13");
INSERT INTO teampass_misc VALUES("admin","ldap_mode","0");
INSERT INTO teampass_misc VALUES("admin","richtext","0");
INSERT INTO teampass_misc VALUES("admin","allow_print","1");
INSERT INTO teampass_misc VALUES("admin","show_description","1");
INSERT INTO teampass_misc VALUES("admin","anyone_can_modify","0");
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
INSERT INTO teampass_misc VALUES("admin","enable_delete_after_consultation","0");
INSERT INTO teampass_misc VALUES("admin","enable_personal_saltkey_cookie","1");
INSERT INTO teampass_misc VALUES("admin","personal_saltkey_cookie_duration","31");
INSERT INTO teampass_misc VALUES("admin","email_smtp_server","smtp.my_domain.com");
INSERT INTO teampass_misc VALUES("admin","email_smtp_auth","false");
INSERT INTO teampass_misc VALUES("admin","email_auth_username","");
INSERT INTO teampass_misc VALUES("admin","email_auth_pwd","");
INSERT INTO teampass_misc VALUES("admin","email_port","25");
INSERT INTO teampass_misc VALUES("admin","email_from","");
INSERT INTO teampass_misc VALUES("admin","email_from_name","");
INSERT INTO teampass_misc VALUES("admin","pwd_maximum_length","40");
INSERT INTO teampass_misc VALUES("admin","2factors_authentication","0");
INSERT INTO teampass_misc VALUES("complex","1","25");
INSERT INTO teampass_misc VALUES("complex","2","25");
INSERT INTO teampass_misc VALUES("complex","3","25");
INSERT INTO teampass_misc VALUES("admin","send_stats_time","0");
INSERT INTO teampass_misc VALUES("admin","ldap_ssl","0");
INSERT INTO teampass_misc VALUES("admin","ldap_tls","0");
INSERT INTO teampass_misc VALUES("admin","enable_kb","1");
INSERT INTO teampass_misc VALUES("admin","copy_to_clipboard_small_icons","1");
INSERT INTO teampass_misc VALUES("admin","enable_user_can_create_folders","0");
INSERT INTO teampass_misc VALUES("admin","enable_send_email_on_user_login","0");
INSERT INTO teampass_misc VALUES("admin","enable_email_notification_on_item_shown","0");
INSERT INTO teampass_misc VALUES("settings","bck_script_filename","bck_cpassman");
INSERT INTO teampass_misc VALUES("settings","bck_script_path","C:/nils.laumaille/utils/xampp/htdocs/teampass/backups");
INSERT INTO teampass_misc VALUES("settings","bck_script_key","");
INSERT INTO teampass_misc VALUES("admin","insert_manual_entry_item_history","1");
INSERT INTO teampass_misc VALUES("complex","8","25");



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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

INSERT INTO teampass_nested_tree VALUES("1","0","F1","13","16","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("2","0","F2","17","18","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("3","1","F1_1","14","15","2","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("4","0","2","3","4","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("5","0","3","5","6","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("6","0","1","1","2","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("7","0","4","7","8","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("8","0","ProjectClient01","19","20","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("9","0","5","9","10","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("10","0","6","11","12","1","0","0","1","0");



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
  `creator_id` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

INSERT INTO teampass_roles_title VALUES("1","users","0","0","0");
INSERT INTO teampass_roles_title VALUES("2","managers","0","0","0");
INSERT INTO teampass_roles_title VALUES("3","Helpdesk","0","25","0");
INSERT INTO teampass_roles_title VALUES("4","Level2","0","25","0");
INSERT INTO teampass_roles_title VALUES("5","ProjectClient01_MNGT","0","0","0");
INSERT INTO teampass_roles_title VALUES("6","test_role","0","25","5");
INSERT INTO teampass_roles_title VALUES("7","users","0","0","5");
INSERT INTO teampass_roles_title VALUES("8","test_my_role","0","25","1");



DROP TABLE teampass_roles_values;

CREATE TABLE `teampass_roles_values` (
  `role_id` int(12) NOT NULL,
  `folder_id` int(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO teampass_roles_values VALUES("1","1");
INSERT INTO teampass_roles_values VALUES("1","3");
INSERT INTO teampass_roles_values VALUES("1","2");
INSERT INTO teampass_roles_values VALUES("2","1");
INSERT INTO teampass_roles_values VALUES("2","2");
INSERT INTO teampass_roles_values VALUES("4","1");
INSERT INTO teampass_roles_values VALUES("4","3");
INSERT INTO teampass_roles_values VALUES("4","2");
INSERT INTO teampass_roles_values VALUES("5","8");
INSERT INTO teampass_roles_values VALUES("3","1");
INSERT INTO teampass_roles_values VALUES("4","8");



DROP TABLE teampass_tags;

CREATE TABLE `teampass_tags` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `tag` varchar(30) NOT NULL,
  `item_id` int(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

INSERT INTO teampass_tags VALUES("1","test","1");
INSERT INTO teampass_tags VALUES("2","item","1");



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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;

INSERT INTO teampass_users VALUES("1","admin","RBo+yAFynKP9VPqMRYLN/lgzaFIOiYJIY43v9pCgZYg=","","","nWAHEM5zAhY0G128qiYL9GtlZl8Je3VLosT9ZO5N1gEe1hADN2","1354492800","","1","","","1354764932","0","","","","1","0","0","0","0","1354764985","english");
INSERT INTO teampass_users VALUES("2","U1","RBo+yAFynKP9VPqMRYLN/lgzaFIOiYJIY43v9pCgZYg=","0","","","1354492800","","0","1","0","1354763900","0","sd@sd.net","","3;2;1","1","0","0","0","0","1354763902","english");
INSERT INTO teampass_users VALUES("3","U2","RBo+yAFynKP9VPqMRYLN/lgzaFIOiYJIY43v9pCgZYg=","0","","","","","0","5","0","","0","qsd@qsd.bet","","","1","0","0","0","0","0","english");
INSERT INTO teampass_users VALUES("4","M1","RBo+yAFynKP9VPqMRYLN/lgzaFIOiYJIY43v9pCgZYg=","","","","1354492800","","0","2","","1354537417","1","sdsd@sd.net","","","1","0","0","0","0","1354537428","english");
INSERT INTO teampass_users VALUES("5","gigi","RBo+yAFynKP9VPqMRYLN/lgzaFIOiYJIY43v9pCgZYg=","","","","1354492800","","0","5","","1354687719","1","test@sqd.net","","","1","0","0","0","0","1354687931","english");
INSERT INTO teampass_users VALUES("6","test_user","RBo+yAFynKP9VPqMRYLN/lgzaFIOiYJIY43v9pCgZYg=","0","","","","","0","0","0","","0","qd@sd.net","","","1","0","0","0","0","0","english");



