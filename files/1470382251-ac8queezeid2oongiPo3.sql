DROP TABLE teampass_api;

CREATE TABLE `teampass_api` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `type` varchar(15) NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `timestamp` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_automatic_del;

CREATE TABLE `teampass_automatic_del` (
  `item_id` int(11) NOT NULL,
  `del_enabled` tinyint(1) NOT NULL,
  `del_type` tinyint(1) NOT NULL,
  `del_value` varchar(35) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_cache;

CREATE TABLE `teampass_cache` (
  `id` int(12) NOT NULL,
  `label` varchar(500) DEFAULT NULL,
  `description` text NOT NULL,
  `tags` text NOT NULL,
  `id_tree` int(12) NOT NULL,
  `perso` tinyint(1) NOT NULL,
  `restricted_to` varchar(200) NOT NULL,
  `login` varchar(200) NOT NULL,
  `folder` varchar(300) NOT NULL,
  `author` varchar(50) NOT NULL,
  `renewal_period` tinyint(4) NOT NULL DEFAULT '0',
  `timestamp` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO teampass_cache VALUES("1","TestItem","","","467","0","","blah","","2","0","1467131369");
INSERT INTO teampass_cache VALUES("2","test 12&quot;","dsf d","","3","0","","yop","","2","0","1467453052");
INSERT INTO teampass_cache VALUES("3","new 1234","","","3","0","","","","2","0","1467453123");
INSERT INTO teampass_cache VALUES("4","User1","test user1","","7","0","","User01","","2","0","1467467809");
INSERT INTO teampass_cache VALUES("5","User2","test user2","","7","0","","User02","","2","0","1467467809");
INSERT INTO teampass_cache VALUES("6","viadeo","","","11","0","","nils.laumaille@gmail.com","","2","0","1467467862");
INSERT INTO teampass_cache VALUES("7","Sample Entry","Notes","","14","0","","User Name","","2","0","1467467862");
INSERT INTO teampass_cache VALUES("8","Serena Admin","","","15","0","","nils.laumaille","","2","0","1467467862");
INSERT INTO teampass_cache VALUES("9","Serena","","","15","0","","nils.laumaille","","2","0","1467467862");
INSERT INTO teampass_cache VALUES("10","SAP","","","15","0","","LAUMAILN","","2","0","1467467862");
INSERT INTO teampass_cache VALUES("11","Doors NL","","","15","0","","nils.laumaille","","2","0","1467467862");
INSERT INTO teampass_cache VALUES("12","Doors Admin","","","15","0","","Administrator","","2","0","1467467862");
INSERT INTO teampass_cache VALUES("13","Atego","","","15","0","","nils.laumaille@valeo.com","","2","0","1467467863");
INSERT INTO teampass_cache VALUES("14","sodius DOORS","","","15","0","","NLA","","2","0","1467467863");
INSERT INTO teampass_cache VALUES("15","Intercall","","","15","0","","7882948617","","2","0","1467467863");
INSERT INTO teampass_cache VALUES("16","ZADIG","","","15","0","","nlaumaille-q5z","","2","0","1467467863");
INSERT INTO teampass_cache VALUES("17","Valeo Mobility","https://europe.connect.valeo.com/NChttps://usa.connect.valeo.com/NChttps://asia.connect.valeo.com/NC","","15","0","","nils.laumaille","","2","0","1467467863");
INSERT INTO teampass_cache VALUES("18","ePLM","","","15","0","","nils.laumaille","","2","0","1467467863");
INSERT INTO teampass_cache VALUES("19","Amadeus","","","15","0","","NILS.LAUMAILLE","","2","0","1467467863");
INSERT INTO teampass_cache VALUES("20","images-maps.com","","","15","0","","nils.laumaille@valeo.com","","2","0","1467467863");
INSERT INTO teampass_cache VALUES("21","http://members.000webhost.com/login.php","000webhost.com =>cPassMan","","2","0","","nils.laumaille@gmail.com","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("22","http://192.168.1.1/","192.168.1.1","","2","0","","admin","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("23","https://192.168.1.15/www/index.php","192.168.1.15","","2","0","","nils","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("24","https://192.168.1.15/www/index.php","192.168.1.15","","2","0","","nils","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("25","https://admin.1and1.fr","1and1.fr","","2","0","","9580729","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("26","https://admin.1and1.fr/xml/config/Login;jsessionid=61CF4FE3A10CCE6BAA6E045850579EED.TC203b?__reuse=1234263665808&__frame=","1and1.fr","","2","0","","9580729","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("27","https://admin.1and1.fr/xml/config/Login;jsessionid=D24F04B7C59E7C21079E820FADAFE06C.TCpfix94b?__reuse=1234263665808&__frame=","1and1.fr (2)","","2","0","","9580729","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("28","http://cpanel.1free.ws/panel/index.php?id=ee223e4a3250ef372efc9ef79ebda45e25496d22","1FREE","","2","0","","freew_6355215","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("29","http://209.200.232.190:2082/","209.200.232.190 - cPANEL","","2","0","","teampass","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("30","http://217.71.125.140","217.71.125.140","","2","0","","root","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("31","http://217.71.125.140/phpmyadmin/","217.71.125.140","","2","0","","root","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("32","http://217.71.125.140","217.71.125.140","","2","0","","root","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("33","http://www.2freehosting.com","2freehosting.com","","2","0","","nilau@gmx.fr","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("34","https://62.23.136.131:8443/eDoc/actions/j_security_check","62.23.136.131","","2","0","","nlaumaille","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("35","https://78.217.192.208/www/tst.php","78.217.192.208","","2","0","","nils","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("36","https://78.217.192.208/www/tst.php","78.217.192.208","","2","0","","admin","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("37","http://80.245.41.50","80.245.41.50","","2","0","","root","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("38","https://82.239.190.80:5001/","82.239.190.80 - SYNOP - Admin","","2","0","","","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("39","http://82.66.7.209:5000/index.cgi","82.66.7.209","","2","0","","","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("40","http://88.187.43.251/VW/","88.187.43.251","","2","0","","visiteur","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("41","http://9giga.sfr.fr/","9giga.sfr.fr","","2","0","","nilslaumaille","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("42","https://account.sonyentertainmentnetwork.com/liquid/login.action","account.sonyentertainmentnetwork.com","","2","0","","nils@laumaille.fr","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("43","https://accounts.google.com/ServiceLoginAuth","accounts.google.com","","2","0","","nils.cpassman.org@gmail.com","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("44","http://clinsight.fr/CitrixAccess/auth/login.aspx?NFuse_MessageType=Error&NFuse_MessageKey=InvalidCredentials&NFuse_LogEventID=&NFuse_MessageArgs=","Accès Citrix (contact)","","2","0","","contact","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("45","http://82.239.190.80:8026/","accès FTP lilo 82.239.190.80","","2","0","","nlaumaille","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("46","https://www.addthis.com","addthis","","2","0","","nils@teampass.net","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("47","http://forum.clinsight.fr/index.php?action=admin","ADMIN - forum.clinsight.fr","","2","0","","","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("48","http://qualite.clinsight.eu/admin/admin.php","Admin Qualité","","2","0","","nla","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("49","https://admin.1and1.fr/xml/config/Login;jsessionid=C81587110BAAED0FFD00EB49F50B8E24.TCpfix92a?__reuse=1350584176935","admin.1and1.fr","","2","0","","191219193","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("50","http://www.adnauto.fr","adnauto.fr","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("51","http://www.adserverpub.com/fr/signup/","adserverpub.com","","2","0","","webmaster@vag-technique.fr","","2","0","1467574697");
INSERT INTO teampass_cache VALUES("52","http://adunblock.com/connexion","adunblock.com","","2","0","","webmaster@vag-technique.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("53","https://adunblock.freshdesk.com","adunblock.freshdesk.com","","2","0","","NilS","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("54","http://www.affiliation-france.com","affiliation-france.com","","2","0","","webmaster@vag-technique.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("55","http://www.affilies.biz/","affilies.biz","","2","0","","vagtechnique","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("56","https://login.yahoo.com/config/login_verify2?","afrais@yahoo.fr","","2","0","","fercecile","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("57","http://www.allopneus.com/Commande-identification.html","allopneus.com","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("58","http://www.allopneus.com/200804_call_retour.asp?myrefcom=956299&paymode=cb","allopneus.com","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("59","http://www.allotraffic.com/","allotraffic.com","","2","0","","slinouille@vag-technique.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("60","http://www.allovitres.com","allovitres.com","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("61","http://www.alltricks.fr/fr/mon-compte/login.php?ret=0&redirect=","alltricks.fr","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("62","https://www.amazon.fr/gp/cart/view.html/ref=ox_sc_proceed","amazon.fr","","2","0","","","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("63","https://www.amundi-ee.com/part/home","amundi-ee.com","","2","0","","13321007","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("64","https://app.oneall.com/signin/","app.oneall.com","","2","0","","nils@teampass.net","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("65","http://www.audipassion.com/services/forums/index.php?s=9c3f689ddaad7102b0ce985e21754fed&act=Login&CODE=00","audipassion.com","","2","0","","slinouille","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("66","https://login.yahoo.com/config/login?","Aurelie.laumaille - yahoo.com","","2","0","","aurelie.laumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("67","https://www.google.com/accounts/ServiceLoginAuth?service=mail","Aurelie.laumaille@gmail.com","","2","0","","aurelie.laumaille@gmail.com","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("68","http://www.bestofdigital.com/","bestofdigital.com","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("69","http://www.bike-parts.fr/user.php?stop=1","bike-parts.fr","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("70","http://www.binnews.info/binnewz/index.php?app=core&module=global&section=login","binnews.info","","2","0","","boubourse","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("71","http://www.binnewsgroup.com/binnewz/index.php?act=login&CODE=00","binnewsgroup.com","","2","0","","gaspart","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("72","https://www.bitgo.com/newaccount","bitgo.com","","2","0","","","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("73","https://www.bitgo.com/signup","bitgo.com","","2","0","","nils@teampass.net","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("74","http://bridgez.net:3002/playonthecloud","bridgez.net","","2","0","","jl-laumaille@orange.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("75","https://www.cacert.org","cacert.org - Certificat SSL","","2","0","","nils.laumaille@gmail.com","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("76","https://secure-espaceclient-canalsatellite.canal-plus.com/index.php?tpl=10&init=1&pid=3185&cid=260990&mess_page=7","canalSat","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("77","http://www.canalsat.fr/","canalsat.fr","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("78","http://80.245.41.50/prive/qualite/index.php?page=capa","CAPA","","2","0","","nla","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("79","http://qualite.clinsight.eu/index.php?page=capa","CAPA","","2","0","","nla","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("80","http://www.carrieres.areva.com/scripts/careers/publigen/content/templates/show.asp?P=214&L=FR","carrieres.areva.com","","2","0","","nlaumaille","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("81","http://www.cdiscount.com/order/address/client.asp?backJ=&mscssid=090417185812ZTWSFQWYXMQZIXK22455","cdiscount.com","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("82","http://chaudieresetco.000a.biz/admin666/login.php?redirect=index.php","chaudieresetco.000a.biz","","2","0","","ajc.bat.ebay@gmail.com","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("83","https://www.co.cc","chaudieresetco.co.cc","","2","0","","ajc.bat.ebay@gmail.com","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("84","http://www.chu-poitiers.fr/8a6b236d-a6e5-45fd-ba2a-6dc920d99bb4.aspx","chu-poitiers.fr","","2","0","","APHV","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("85","http://www.clicmanager.fr/devenir_editeur.php","clicmanager.fr","","2","0","","slinouille@vag-technique.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("86","http://webmail.vag-technique.fr/compose.php?replyto=43882-%40-INBOX","clinsight.fr","","2","0","","nla","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("87","http://www.clustrmaps.com/fr/admin/action.php","clustrmaps.com","","2","0","","http://www.teampass.net","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("88","https://espaceclient.maaf.fr/WebClient/webclient/Migration.do","COFFRE FORT - espaceclient.maaf.fr","","2","0","","86069651","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("89","https://coinbase.com/signup","coinbase.com","","2","0","","nils@teampass.net","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("90","https://www.colissimo.fr","colissimo.fr","","2","0","","nlaumaille@yahoo.fr","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("91","http://contact.clinsight.eu/CitrixAccess/auth/login.aspx","contact.clinsight.eu CONTACT","","2","0","","contact","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("92","https://control.rocketvps.com:5656/login.php","control.rocketvps.com","","2","0","","rvps376","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("93","https://gator3200.hostgator.com:2083/","cpanel - cpassman.hostgator.com","","2","0","","cpassman","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("94","https://gator1434.hostgator.com:2083/","cPanel HostGator","","2","0","","cpassman","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("95","http://cpanel.2freehosting.com","cpanel.2freehosting.com -> Teampass","","2","0","","bopa6","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("96","https://gotit.us:2083/","cpanel.gotit.us","","2","0","","gotitus","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("97","http://cpassman.net23.net/forum","cpassman.net23.net -> FORUM","","2","0","","Nils","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("98","http://www.cpassman.org/lang","cpassman.org","","2","0","","nils","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("99","http://cpassman.pbworks.com/w/session/login?lf=1&email=nils.laumaille%40gmail.com","cpassman.pbworks.com","","2","0","","nils.laumaille@gmail.com","","2","0","1467574698");
INSERT INTO teampass_cache VALUES("100","qdqs sqd ","","","3","0","","","","2","0","1467578978");
INSERT INTO teampass_cache VALUES("101","qsdqsd","","","1","0","","","","2","0","1468349985");
INSERT INTO teampass_cache VALUES("102","#1382","2.1.24 - Parsing error when adding password with two underscore characters","","1","0","","","","2","0","1468767316");
INSERT INTO teampass_cache VALUES("103","qsdqsd","","","12","0","","","","2","0","1469044556");
INSERT INTO teampass_cache VALUES("104","http://members.000webhost.com/login.php","000webhost.com =>cPassMan","","14","0","","nils.laumaille@gmail.com","Recycle Bin","2","0","1469787322");
INSERT INTO teampass_cache VALUES("105","http://192.168.1.1/","192.168.1.1","","14","0","","admin","Recycle Bin","2","0","1469787322");
INSERT INTO teampass_cache VALUES("106","https://192.168.1.15/www/index.php","192.168.1.15","","14","0","","nils","Recycle Bin","2","0","1469787322");
INSERT INTO teampass_cache VALUES("107","my1","fdsf","","39","1","","","U1","2","0","1470378334");
INSERT INTO teampass_cache VALUES("108","my2","qsd sqd","","40","1","","","U1 » U1_2","2","0","1470378356");



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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_categories_folders;

CREATE TABLE `teampass_categories_folders` (
  `id_category` int(12) NOT NULL,
  `id_folder` int(12) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_categories_items;

CREATE TABLE `teampass_categories_items` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `field_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `data` text NOT NULL,
  `data_iv` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_emails;

CREATE TABLE `teampass_emails` (
  `timestamp` int(30) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `receivers` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_export;

CREATE TABLE `teampass_export` (
  `id` int(12) NOT NULL,
  `label` varchar(255) NOT NULL,
  `login` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `pw` text NOT NULL,
  `path` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_files;

CREATE TABLE `teampass_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_item` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `size` int(10) NOT NULL,
  `extension` varchar(10) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `file` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

INSERT INTO teampass_files VALUES("1","1","manuel_gazini_re.pdf","375815","pdf","application/pdf","adba552bcf605986c02ea92fe1e26fef");
INSERT INTO teampass_files VALUES("2","1","3008_compresseur_2.docx","13767","docx","application/vnd.openxmlformats-officedocument.wordprocessingml.document","7773f9d50ac3725b2dd5b8a74aabf9e5");
INSERT INTO teampass_files VALUES("3","6","_623459467_trail_chauvigny_12kms.xlsx","31883","xlsx","application/vnd.openxmlformats-officedocument.spreadsheetml.sheet","1896949eabd88355f6016c66482fe1e7");
INSERT INTO teampass_files VALUES("4","1","2014_11_25_20_37_36_flyer.pub_publisher.png","19921","png","image/png","caf047366fbebdee566fec1bf5c6a628");
INSERT INTO teampass_files VALUES("5","31","apache_pb2.png","1463","png","image/png","9c4e5d1faaf21bac44df8cc72cd3c3a5");
INSERT INTO teampass_files VALUES("6","392689171","apache_pb.gif","2326","gif","image/gif","d759168c24cd716bbbe0f9e08cb274b9");
INSERT INTO teampass_files VALUES("7","100","apache_pb.gif","2326","gif","image/gif","32a5d21c2518ad92def96bee61cfed7e");
INSERT INTO teampass_files VALUES("8","100","apache_pb.png","1385","png","image/png","c98942c888fdfb70c3aa1d1538d83bef");
INSERT INTO teampass_files VALUES("9","100","apache_pb2.gif","2414","gif","image/gif","9286e8351913e59187b5184dd50ca2ad");
INSERT INTO teampass_files VALUES("10","31","apache_pb.png","1385","png","image/png","b8a04a7b6e36c163edbd1be46a67a23a");
INSERT INTO teampass_files VALUES("11","32","apache_pb.gif","2326","gif","image/gif","839504d06cb37a06b1a0ec10552489a2");
INSERT INTO teampass_files VALUES("12","30","apache_pb.png","1385","png","image/png","55f6de80594a899380cfce9790a48f7e");
INSERT INTO teampass_files VALUES("13","30","apache_pb2.gif","2414","gif","image/gif","3fefc67c0c968089b901292d5e7e1fca");
INSERT INTO teampass_files VALUES("14","30","apache_pb2.png","1463","png","image/png","74375815577c4a3505aa6623a1839c07");
INSERT INTO teampass_files VALUES("15","22","2014_11_06_19_18_40_collaborative_passwords_manager.png","9828","png","image/png","3b6c73d24729c27e72720426f76ae238");
INSERT INTO teampass_files VALUES("16","22","2014_11_25_20_37_36_flyer.pub_publisher.png","19921","png","image/png","ec9bfaeae21c96110ccc3e21b7946bfa");
INSERT INTO teampass_files VALUES("17","22","2014_12_30_08_33_43_vag_technique.fr.png","62673","png","image/png","72734d931f24cfee0da87935f7a4e02c");
INSERT INTO teampass_files VALUES("18","22","2015_01_25_11_27_32_collaborative_passwords_manager.png","188218","png","image/png","b6618594073ea4ac5e7447f8149fc399");



DROP TABLE teampass_items;

CREATE TABLE `teampass_items` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `label` varchar(500) DEFAULT NULL,
  `description` text,
  `pw` text,
  `pw_iv` text,
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
  `complexity_level` varchar(3) DEFAULT NULL,
  `auto_update_pwd_frequency` tinyint(2) NOT NULL DEFAULT '0',
  `auto_update_pwd_next_date` int(15) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `restricted_inactif_idx` (`restricted_to`,`inactif`)
) ENGINE=MyISAM AUTO_INCREMENT=109 DEFAULT CHARSET=utf8;

INSERT INTO teampass_items VALUES("1","TestItem","","75d227a99b1cce6c84954410dbadc2ee","2152528f081318e9a1343097eba79565","0","","467","0","blah","0","","1","","","15","100","0","0");
INSERT INTO teampass_items VALUES("2","test 12&quot;","dsf d","79d50db7682c229a7b823abe7aaecad7","78511c5709b93b0f773db278c70f3f4e","0","","3","0","yop","0","","0","sdq@sd.net","","31","64","0","0");
INSERT INTO teampass_items VALUES("3","new 1234","","dea20cae1cf4aaa8c1ffdba63fb53dbe14f8564b6597ae19a83443e7665b7fda","1a58c353cc77b8835a3e405909452902","0","","3","0","","0","","0","","","6","100","0","0");
INSERT INTO teampass_items VALUES("4","User1","test user1","a7541bb305ef180cc4772346c5a1f958","c55575c7b6977fb60c7df1a20b4c7262","0","","7","0","User01","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("5","User2","test user2","11e26afc246f4ee16a67c44589dae878","470a2a69e19432b049ed46cd38168f3f","0","","7","0","User02","0","","0","","","3","-1","0","0");
INSERT INTO teampass_items VALUES("6","viadeo","","9620624a6f995c4f19a06cee67bfe8c7","8d04a59695e84fe07b38cdac36bbd353","0","http://www.viadeo.com/fr/connexion","11","0","nils.laumaille@gmail.com","0","","0","","","28","27","0","0");
INSERT INTO teampass_items VALUES("7","Sample Entry","Notes","ce3f202d537d00a639c0ae547bafcd85","d32158c5a567bc5df1a504606a1ee217","0","http://www.somesite.com/","14","0","User Name","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("8","Serena Admin","","e91115763a1dbe2612977471e754469b","604b460f039e6e7d42e663af0415469d","0","http://bie3-sv00013.vnet.valeo.com:8080/adminconsole/?jsp=login","15","0","nils.laumaille","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("9","Serena","","dc9d02c511f421f0bb8599cbe0cd5ffb","650c3d9352a118633cee01e0dcfbc26e","0","http://bie3-sv00013:8080/dimensions/?jsp=login","15","0","nils.laumaille","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("10","SAP","","5913aafe60dec6eaeedec8e8f5ea8d97","a744367d837db8389741a427238bfc1f","0","","15","0","LAUMAILN","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("11","Doors NL","","994579adc92eab263ada352fd27308b1","6f2d2d1fbfbe38eeaff743f1cfb6c4c7","0","","15","0","nils.laumaille","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("12","Doors Admin","","9758fc49e283e1b952f9563344a0375b","d2aa7b88c9425dfc98a3a580851e59ef","0","","15","0","Administrator","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("13","Atego","","fc032e51f5acbb388c8d742c1139a6a62818dbd77055846f914fd10ec531b56d","d6db00440975a526cd82ab8057d27123","0","","15","0","nils.laumaille@valeo.com","0","","0","","","4","-1","0","0");
INSERT INTO teampass_items VALUES("14","sodius DOORS","","15a5fd319672bfb54d7dbee01285438d23d1043cf6e843b5f67ddce28612d0d6","9e90d23c37ad201e196fc1f61756a309","0","http://sodius.com/wp-login.php","15","0","NLA","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("15","Intercall","","9e47b26a53aea914f51d1f345a1f232f","2b3a5365aea74b169c3cdd2512a7da24","0","","15","0","7882948617","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("16","ZADIG","","acd0f23a379baeab72f9a1adfaa44462","486132221a91b6437364002d825f3c2d","0","http://www.zadig-tge.adp.com/","15","0","nlaumaille-q5z","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("17","Valeo Mobility","https://europe.connect.valeo.com/NChttps://usa.connect.valeo.com/NChttps://asia.connect.valeo.com/NC","c9d27e60653545d91bded4842cfd57bf","57d6227fdf94961b2b213b476e55817c","0","https://europe.connect.valeo.com/NC","15","0","nils.laumaille","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("18","ePLM","","57093b7521a4b40683530c2fb2fecfd7","3d310bc3fa1b447c99dcebcdea5c646f","0","","15","0","nils.laumaille","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("19","Amadeus","","d928cb07a2ac1219f1c794348710f525","3f07d3a466e869a96c031b23683ad60d","0","","15","0","NILS.LAUMAILLE","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("20","images-maps.com","","99a5d8c78c1dcd83006a384ec86291c9f8b5bf2656bd997c8c6f18137e35db6a","4b3ea0c5add7d72a7fbb035989ce63f7","0","","15","0","nils.laumaille@valeo.com","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("21","http://members.000webhost.com/login.php","000webhost.com =>cPassMan","ab8151220d9bbddec3a8c6749fc49de6","c3b5943809826a207b2bfe73f22da3fb","0","","2","0","nils.laumaille@gmail.com","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("22","http://192.168.1.1/","192.168.1.1","01db22c6e54e0b2e600585565a94d752","de4193773fe4d57ec04d17556aa523a3","0","","2","0","admin","0","","0","","","5","82","0","0");
INSERT INTO teampass_items VALUES("23","https://192.168.1.15/www/index.php","192.168.1.15","b81f587f471d23eb5495c4bbdb1ca7be","9e03001cd0386e536afd3e4a3900d119","0","","2","0","nils","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("24","https://192.168.1.15/www/index.php","192.168.1.15","eb9fa9c06984296c759987e382540313","5cc5a47b7de3e314d7ce2547b6b6ab17","0","","2","0","nils","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("25","https://admin.1and1.fr","1and1.fr","da66c79d0befa7e0008008d87a218d19","5612989c4086a79383e28587a2499ad1","0","","2","0","9580729","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("26","https://admin.1and1.fr/xml/config/Login;jsessionid=61CF4FE3A10CCE6BAA6E045850579EED.TC203b?__reuse=1234263665808&__frame=","1and1.fr","2344acc27c6176fcf7bd32caad439d0a","c63ce16d73525d096bf996c9a0e5e1b3","0","","2","0","9580729","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("27","https://admin.1and1.fr/xml/config/Login;jsessionid=D24F04B7C59E7C21079E820FADAFE06C.TCpfix94b?__reuse=1234263665808&__frame=","1and1.fr (2)","0690d0e3a987f38a43386120e5676aa3","e03181162ac55b0c3e0882b367ba39c6","0","","2","0","9580729","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("28","http://cpanel.1free.ws/panel/index.php?id=ee223e4a3250ef372efc9ef79ebda45e25496d22","1FREE","d235f94acb559f25afb82f09b77b4fd0","2e4f57a4311a8946568af66acc3697cb","0","","2","0","freew_6355215","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("29","http://209.200.232.190:2082/","209.200.232.190 - cPANEL","9ba65d8a1d600b24b3942332d45d5762","a5761dfd9d5b2d54f2d9d5251e4446cf","0","","2","0","teampass","0","","0","","","10","55","0","0");
INSERT INTO teampass_items VALUES("30","http://217.71.125.140","217.71.125.140","71307d8f2c0ba8b69ae0e2bfc3061ed2","418706f9c1e9fe524e9e33ba437ea1fa","0","","2","0","root","0","","0","","","5","-1","0","0");
INSERT INTO teampass_items VALUES("31","http://217.71.125.140/phpmyadmin/","217.71.125.140","06a8b921f4ac46cc20d156fc47044896","5b3a655f6eccbc1956c6ff558aae2a7c","0","","2","0","root","0","","0","","","6","-1","0","0");
INSERT INTO teampass_items VALUES("32","http://217.71.125.140","217.71.125.140","","f50e384ed0eefbe54a00b451235c19df","0","","2","0","root","0","","0","","","5","-1","0","0");
INSERT INTO teampass_items VALUES("33","http://www.2freehosting.com","2freehosting.com","325973f564051a6e5e692fcecf4e30a9","3b95a2069b578078db65829dbb830dc3","0","","2","0","nilau@gmx.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("34","https://62.23.136.131:8443/eDoc/actions/j_security_check","62.23.136.131","3fcf7df203dab13bf3140c9381271ce2","059b3aa14e1797eafe845343396a6737","0","","2","0","nlaumaille","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("35","https://78.217.192.208/www/tst.php","78.217.192.208","8084ca76d8990c6504f5384c684ddc31","445acfb0fe64300dcaaea1f7c4c10c78","0","","2","0","nils","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("36","https://78.217.192.208/www/tst.php","78.217.192.208","909ff986c31893b5661fdc2ec501a082","701ac48e062b7e20c6cc8a5773d11c10","0","","2","0","admin","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("37","http://80.245.41.50","80.245.41.50","3a82a5f9af21952167651143a54445ca","21e5f682f1540e847554817856f84624","0","","2","0","root","0","","0","","","2","-1","0","0");
INSERT INTO teampass_items VALUES("38","https://82.239.190.80:5001/","82.239.190.80 - SYNOP - Admin","93f3d96b10c92d3a8b7915732e83f500","bff14857a2a7ff52e4c9dca159a857ac","0","","2","0","","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("39","http://82.66.7.209:5000/index.cgi","82.66.7.209","25955643eb94b423e28dac62087cf7dc","f7cf64a6389ef08856b6a4ae5d317027","0","","2","0","","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("40","http://88.187.43.251/VW/","88.187.43.251","7387c624fbbb9d298de3051770e72790","4341ec645f8762d468fce89096ff411d","0","","2","0","visiteur","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("41","http://9giga.sfr.fr/","9giga.sfr.fr","d7126b8c1d25f41f6529b3f9b9ff07bc","5ca149dc27985eab6dab446263a77abf","0","","2","0","nilslaumaille","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("42","https://account.sonyentertainmentnetwork.com/liquid/login.action","account.sonyentertainmentnetwork.com","17441cb746588d0997f001155c4ddf9b","b8d945c38c40bb712bb3838319f3e9bd","0","","2","0","nils@laumaille.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("43","https://accounts.google.com/ServiceLoginAuth","accounts.google.com","7e832ffbac84f1a975803780c56eb4ed","abb7fcc5bca50d8516147217b8fdda58","0","","2","0","nils.cpassman.org@gmail.com","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("44","http://clinsight.fr/CitrixAccess/auth/login.aspx?NFuse_MessageType=Error&NFuse_MessageKey=InvalidCredentials&NFuse_LogEventID=&NFuse_MessageArgs=","Accès Citrix (contact)","453838775764b1520eb44ccac00e759e","79bbb2143772796ec87c0461a4ea0124","0","","2","0","contact","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("45","http://82.239.190.80:8026/","accès FTP lilo 82.239.190.80","fd8f9f4622f42a9c098aa3f88eecb72e","f144f49a06c639c80366c0e43c2469b3","0","","2","0","nlaumaille","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("46","https://www.addthis.com","addthis","1aa8520f37935e9bbf5a318637a09894955d66acc068bbf0926d681eeec6eb3d","8faaf88af184c097fd77496191863db7","0","","2","0","nils@teampass.net","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("47","http://forum.clinsight.fr/index.php?action=admin","ADMIN - forum.clinsight.fr","86460e265c22d915d3e27d067d6959a7","878d134d3e39e19ad6b23d7e8496f235","0","","2","0","","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("48","http://qualite.clinsight.eu/admin/admin.php","Admin Qualité","2ba24989d37dfb5e1c5adf37a198b567","538675433915618eed5ec313c610e046","0","","2","0","nla","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("49","https://admin.1and1.fr/xml/config/Login;jsessionid=C81587110BAAED0FFD00EB49F50B8E24.TCpfix92a?__reuse=1350584176935","admin.1and1.fr","1310ee6fb0ff7c018ea38d6f6c661e0b","0c919f7b393302b39ffc86f6a8d60d87","0","","2","0","191219193","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("50","http://www.adnauto.fr","adnauto.fr","8be3cafc1e0c1300b84b382ac1a23ba5","312a2b796bb4d021c806341280451d73","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("51","http://www.adserverpub.com/fr/signup/","adserverpub.com","a96f16181e45327e24c4803bc29727c450a8ca634f05d3ab53eaa0cea98d99cc","3d2571c7d6f556f8773473daf50f9fa9","0","","2","0","webmaster@vag-technique.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("52","http://adunblock.com/connexion","adunblock.com","236ee8fa413bb8e332f66e5191590b9f","05f838d8042599563c771a252878c917","0","","2","0","webmaster@vag-technique.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("53","https://adunblock.freshdesk.com","adunblock.freshdesk.com","188dd92bcc26affa609ac3528bb2b54b","9df5c205181fb06a140127c4f04ff405","0","","2","0","NilS","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("54","http://www.affiliation-france.com","affiliation-france.com","d0dbf0a24df9bbffd5c3107d9bb745f131f6ac4daae649f26c45a3431e7ef92f","b2dbfff99f7438cf0c527485e4dbc323","0","","2","0","webmaster@vag-technique.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("55","http://www.affilies.biz/","affilies.biz","8c7b33d8f0e231ca69d866debbf4cd277a74d0b7849917bda0b4bb7f255dc27c","c92eec5fed6bfa4f34d11fd61f7ebd55","0","","2","0","vagtechnique","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("56","https://login.yahoo.com/config/login_verify2?","afrais@yahoo.fr","95e948946fe7909ef75651173808decd","b11908f9d8ffcf40a10a7045cc202dd8","0","","2","0","fercecile","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("57","http://www.allopneus.com/Commande-identification.html","allopneus.com","0b87979fb1c1bc74b648b5a60838019d","82d15c6229aa00ef0a12891d868c5294","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("58","http://www.allopneus.com/200804_call_retour.asp?myrefcom=956299&paymode=cb","allopneus.com","5257512d77430ee10ae63f9f230bf250","82de8adf56a7e111d128268721378bd7","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("59","http://www.allotraffic.com/","allotraffic.com","ee9dea01338be764654d0b7e349b0ccd","226d1927b4c4dc2dd42535ff3bc8a966","0","","2","0","slinouille@vag-technique.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("60","http://www.allovitres.com","allovitres.com","2d8a671c16c3b6853a20839ebdb27e95c8cf04fea3ca134a6af2c4ea041f6e36","7c4b2bf11740198a3473200eac5f5487","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("61","http://www.alltricks.fr/fr/mon-compte/login.php?ret=0&redirect=","alltricks.fr","9aa940164b40c9836148e73ba5a58715","c3ec4941b8e6c426a6ce27256283170e","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("62","https://www.amazon.fr/gp/cart/view.html/ref=ox_sc_proceed","amazon.fr","57d79af0ff7fb400553063655f924d29","ee6ef46c26260bcd970bf0816c52bf46","0","","2","0","","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("63","https://www.amundi-ee.com/part/home","amundi-ee.com","306d9d249b783a6fe79618ae5453862e","81842bccf3aee1b892452f70699ec17f","0","","2","0","13321007","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("64","https://app.oneall.com/signin/","app.oneall.com","6b88a6a6e7becc261591dab11df36aad","3dca4fa37c38f9d61877b2b3bf30d9d3","0","","2","0","nils@teampass.net","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("65","http://www.audipassion.com/services/forums/index.php?s=9c3f689ddaad7102b0ce985e21754fed&act=Login&CODE=00","audipassion.com","54c741d0111e1f07bb9b68fb0d196eed","eef1d241d7e25cb41dd5fa46f9c34d0a","0","","2","0","slinouille","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("66","https://login.yahoo.com/config/login?","Aurelie.laumaille - yahoo.com","849b9bdaaabfa784789fbc992a2d4410","19a59e952e229d250099e3cb8f642151","0","","2","0","aurelie.laumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("67","https://www.google.com/accounts/ServiceLoginAuth?service=mail","Aurelie.laumaille@gmail.com","36f14cabb2c8a2a9c07adc696ee1c45e","a1afe6aa5512e101ac14266668a6966f","0","","2","0","aurelie.laumaille@gmail.com","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("68","http://www.bestofdigital.com/","bestofdigital.com","aff21ce0b1a9f8634123e1b45a5484de","d51f19b79e28b74e0a5dc74e6be09d8b","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("69","http://www.bike-parts.fr/user.php?stop=1","bike-parts.fr","29a82e93e0c5d7fb0ff298122b8f6679","9b6cf56c39bede195cfc2a6b3cd02edb","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("70","http://www.binnews.info/binnewz/index.php?app=core&module=global&section=login","binnews.info","9cd362da0b84383e24b80b6edb302d9a","88274f94c902cb182402c3225e0b5c1c","0","","2","0","boubourse","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("71","http://www.binnewsgroup.com/binnewz/index.php?act=login&CODE=00","binnewsgroup.com","929f01f1c1667ad8fe79634fade75268","84ba0f5f66d9a500e3198870c1012f08","0","","2","0","gaspart","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("72","https://www.bitgo.com/newaccount","bitgo.com","ee93fb2084d51f7c00d584146ada05d810583fa36f0a99e74cade3f1f6cb9f3e","9a44bd45af1321d798e45f3bbd781e10","0","","2","0","","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("73","https://www.bitgo.com/signup","bitgo.com","5d31ca0d26554ea27767cc21972c16ecfa3e05246ec5b67453b7ff9facb6e621","ad814f634453f78735f41a42af3f4e66","0","","2","0","nils@teampass.net","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("74","http://bridgez.net:3002/playonthecloud","bridgez.net","bb585753901a8b2f2e4ac2d385570ad0","58af192ed739231aa11e04428353fd82","0","","2","0","jl-laumaille@orange.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("75","https://www.cacert.org","cacert.org - Certificat SSL","8b7944ea3feebc82960f3febf785a812de70b02a83d7083f1d28ee6687330b7a","44b9271dc61417417e7a3c35c134a2d0","0","","2","0","nils.laumaille@gmail.com","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("76","https://secure-espaceclient-canalsatellite.canal-plus.com/index.php?tpl=10&init=1&pid=3185&cid=260990&mess_page=7","canalSat","5f3bb002ed9b9d16a4a571b69caaac3c","aa06d4d087cb7c1d8283a2b6b3f32caf","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("77","http://www.canalsat.fr/","canalsat.fr","941b34e576dc57cc053398466c271b69","8a48a2df2144933fe3b6b473525b1fbc","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("78","http://80.245.41.50/prive/qualite/index.php?page=capa","CAPA","4b715b676d496289884754ec3528b20e","d24eb1adb9eb59280462bcad655fdc07","0","","2","0","nla","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("79","http://qualite.clinsight.eu/index.php?page=capa","CAPA","aca92109ea73b66a51357f687ef2272a","b3903fbed6656e304281eda47be4b9a1","0","","2","0","nla","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("80","http://www.carrieres.areva.com/scripts/careers/publigen/content/templates/show.asp?P=214&L=FR","carrieres.areva.com","331623623002c05d5de7853bcdf8cbac","4eefd2a7ff3b254afbdb78c29214f086","0","","2","0","nlaumaille","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("81","http://www.cdiscount.com/order/address/client.asp?backJ=&mscssid=090417185812ZTWSFQWYXMQZIXK22455","cdiscount.com","651828d24c87c506c5d713cdef836832","384cc484cbdb7bb61fb4c5a4166be55b","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("82","http://chaudieresetco.000a.biz/admin666/login.php?redirect=index.php","chaudieresetco.000a.biz","52e516bd8868e83f9931ed38e6da2558","28bae70809184fd265d1d6822ec24c04","0","","2","0","ajc.bat.ebay@gmail.com","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("83","https://www.co.cc","chaudieresetco.co.cc","fd7d665cd73ac82df2f1f42f175f2fa6","e236b47f607f01dc74e96bc911106cd4","0","","2","0","ajc.bat.ebay@gmail.com","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("84","http://www.chu-poitiers.fr/8a6b236d-a6e5-45fd-ba2a-6dc920d99bb4.aspx","chu-poitiers.fr","e9dcb721092330d21dbc2e9691785ccd","9302db138728b6c0644fd6b59bd9c709","0","","2","0","APHV","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("85","http://www.clicmanager.fr/devenir_editeur.php","clicmanager.fr","4bab19478ef68f132f9a299a9da9a6ac","ad4023fc612f7f483ce42b603de00dd0","0","","2","0","slinouille@vag-technique.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("86","http://webmail.vag-technique.fr/compose.php?replyto=43882-%40-INBOX","clinsight.fr","ab4a7946742fff58f25788de17ba50d1","d4ada38702a2b7ce4fcd10033e328536","0","","2","0","nla","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("87","http://www.clustrmaps.com/fr/admin/action.php","clustrmaps.com","d704d10740bc1c82b253298abf9630eb","98b4b654a1d85fedce81db7aac3bc4bb","0","","2","0","http://www.teampass.net","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("88","https://espaceclient.maaf.fr/WebClient/webclient/Migration.do","COFFRE FORT - espaceclient.maaf.fr","1942779b02dd2a6cc162a3fbfd4a7ed2","0112cf8655a8e4dc74bea3585827acf3","0","","2","0","86069651","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("89","https://coinbase.com/signup","coinbase.com","f66fa3d0205aa6d0470c821e207f54b2186a381ae0685da40135bf96ca5a123f","e514bf13ead6b55edfaa12e93d2992f5","0","","2","0","nils@teampass.net","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("90","https://www.colissimo.fr","colissimo.fr","33b028fe5202221962dfede7cec6bd9f","796cdd1f2ae9da88d1ee1fad3bd5c444","0","","2","0","nlaumaille@yahoo.fr","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("91","http://contact.clinsight.eu/CitrixAccess/auth/login.aspx","contact.clinsight.eu CONTACT","b13e7f804d65d94dd765f093fe8d5120","8988766465cdcb6dbdb0fa1d4575b2b3","0","","2","0","contact","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("92","https://control.rocketvps.com:5656/login.php","control.rocketvps.com","972b841623631d727e166481e0c2bc40","23ac67f653e9bd6365fb9fe4c12bdf3a","0","","2","0","rvps376","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("93","https://gator3200.hostgator.com:2083/","cpanel - cpassman.hostgator.com","0bc5badeaafb7e66b6a1126d080445ff","f929d5e3233e037fe9e6118a996e6974","0","","2","0","cpassman","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("94","https://gator1434.hostgator.com:2083/","cPanel HostGator","8d37cdae53d54ec83702e93229f6ba57","9c4d0f61db2335b8c0e3136165e0396c","0","","2","0","cpassman","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("95","http://cpanel.2freehosting.com","cpanel.2freehosting.com -> Teampass","ff46218980261a3c81f5b17d2abf5d64","586de2853d0d81fb8574fbf27f327323","0","","2","0","bopa6","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("96","https://gotit.us:2083/","cpanel.gotit.us","ef81de136affd13fecd4944e3b27e1a7","9ceba7be7914209dfae5ab25ca8e0f30","0","","2","0","gotitus","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("97","http://cpassman.net23.net/forum","cpassman.net23.net -> FORUM","66cb4deaebdf542a9cd9908c866e965d","9d154c9e42c24af57b4923e7f8345be6","0","","2","0","Nils","0","","0","","","1","-1","0","0");
INSERT INTO teampass_items VALUES("98","http://www.cpassman.org/lang","cpassman.org","75196643d04d921da6750b64c5c4c6ea","e7dfb445d7fc6e869e3d61324a68072c","0","","2","0","nils","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("99","http://cpassman.pbworks.com/w/session/login?lf=1&email=nils.laumaille%40gmail.com","cpassman.pbworks.com","e3729cdbe3a33d210dde2c94dcf9911b","e21f6406dab12353bf0b04e498743482","0","","2","0","nils.laumaille@gmail.com","0","","0","","","0","-1","0","0");
INSERT INTO teampass_items VALUES("100","qdqs sqd ","","a8c7b8f3f88338f39920088a66aef4a8","839bc957add057c54f9ccb384b21856f","0","","3","0","","0","","0","","","6","56","0","0");
INSERT INTO teampass_items VALUES("101","qsdqsd","","cb85cd061a3a131dee8c98ef6abe7ca8","da6d53b40da2af9ab73252241c208f17","0","","1","0","","0","","0","","","31","79","0","0");
INSERT INTO teampass_items VALUES("102","#1382","2.1.24 - Parsing error when adding password with two underscore characters","96706f0d2210f4ac82ccf6765f07d54a","75f8fa9b7a8bb61898a54f2e707406b8","0","","1","0","","0","","0","","","7","96","0","0");
INSERT INTO teampass_items VALUES("103","qsdqsd","","be657d09a6f3481c5c8a93092b403c0d","6cec63115c06b0ca98a24582a13d2eda","0","","12","0","","0","","0","","","1","58","0","0");
INSERT INTO teampass_items VALUES("104","http://members.000webhost.com/login.php","000webhost.com =>cPassMan","b4de04076c4cd32cf08d3449bff5c2fb","a27854668a8af086e48c29f2b9b54b6f","0","","14","0","nils.laumaille@gmail.com","0","","0","","","0","","0","0");
INSERT INTO teampass_items VALUES("105","http://192.168.1.1/","192.168.1.1","67fadcb13a6dea764e84d2e486735d00","9df2f3c22cbb7a06fdb0f1469f634f4d","0","","14","0","admin","0","","0","","","0","","0","0");
INSERT INTO teampass_items VALUES("106","https://192.168.1.15/www/index.php","192.168.1.15","8f28edad5a76af6f3ca110d2cabac103","387c19dfc23013c5c610c1a39bf74156","0","","14","0","nils","0","","0","","","0","","0","0");
INSERT INTO teampass_items VALUES("107","my1","fdsf","f3c40aa315e2ea06fbf91fa5f85cd7b2","71671f131565ecb86c82cc632ca5a4af","0","","39","1","","0","","0","","","1","45","0","0");
INSERT INTO teampass_items VALUES("108","my2","qsd sqd","934e3f8ee77a857078ad4fa12a12021b","71af1ce27132880b82bece28abb55af4","0","","40","1","","0","","0","","","1","52","0","0");



DROP TABLE teampass_items_edition;

CREATE TABLE `teampass_items_edition` (
  `item_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `timestamp` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO teampass_items_edition VALUES("29","2","1467577611");
INSERT INTO teampass_items_edition VALUES("32","2","1467663708");
INSERT INTO teampass_items_edition VALUES("37","2","1467578135");
INSERT INTO teampass_items_edition VALUES("31","2","1467663574");
INSERT INTO teampass_items_edition VALUES("2","2","1468860835");
INSERT INTO teampass_items_edition VALUES("107","2","1470378338");



DROP TABLE teampass_kb;

CREATE TABLE `teampass_kb` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `category_id` int(12) NOT NULL,
  `label` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `author_id` int(12) NOT NULL,
  `anyone_can_modify` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_kb_categories;

CREATE TABLE `teampass_kb_categories` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_kb_items;

CREATE TABLE `teampass_kb_items` (
  `kb_id` tinyint(12) NOT NULL,
  `item_id` tinyint(12) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_languages;

CREATE TABLE `teampass_languages` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `label` varchar(50) NOT NULL,
  `code` varchar(10) NOT NULL,
  `flag` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=18 DEFAULT CHARSET=utf8;

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
INSERT INTO teampass_languages VALUES("17","estonian","Estonian","ee","ee.png");



DROP TABLE teampass_log_items;

CREATE TABLE `teampass_log_items` (
  `id_item` int(8) NOT NULL,
  `date` varchar(50) NOT NULL,
  `id_user` int(8) DEFAULT NULL,
  `action` varchar(250) DEFAULT NULL,
  `raison` text,
  `raison_iv` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO teampass_log_items VALUES("1","1467131369","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("1","1467131369","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467131371","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467131372","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467146230","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467146249","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467453003","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467453017","2","at_modification","at_pw :adf28740272b0732935545e85fae28c5","3c936663c57e258545e6fb207ddd8777");
INSERT INTO teampass_log_items VALUES("2","1467453052","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("2","1467453053","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1467453123","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("3","1467453124","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467454424","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467454546","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467466385","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467466423","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467466939","2","at_modification","at_add_file : manuel_gazini_re.pdf","");
INSERT INTO teampass_log_items VALUES("1","1467467184","2","at_modification","at_add_file : 3008_compresseur_2.docx","");
INSERT INTO teampass_log_items VALUES("1","1467467266","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467467314","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467467335","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467467532","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("1","1467467533","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("4","1467467809","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("5","1467467809","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("5","1467467818","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("5","1467467820","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467467862","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("7","1467467862","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("8","1467467862","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("9","1467467862","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("10","1467467862","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("11","1467467862","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("12","1467467862","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("13","1467467863","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("14","1467467863","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("15","1467467863","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("16","1467467863","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("17","1467467863","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("18","1467467863","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("19","1467467863","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("20","1467467863","2","at_creation","at_import","");
INSERT INTO teampass_log_items VALUES("6","1467467870","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467467871","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467475255","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467475329","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467475642","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467475711","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467475789","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467475817","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467475847","2","at_modification","at_add_file : _623459467_trail_chauvigny_12kms.xlsx","");
INSERT INTO teampass_log_items VALUES("6","1467475855","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467475919","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467476031","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467476167","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467476939","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467557750","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467557763","2","at_modification","at_add_file : 2014_11_25_20_37_36_flyer.pub_publisher.png","");
INSERT INTO teampass_log_items VALUES("1","1467557767","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("1","1467573790","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("21","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("22","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("23","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("24","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("25","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("26","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("27","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("28","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("29","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("30","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("31","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("32","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("33","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("34","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("35","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("36","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("37","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("38","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("39","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("40","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("41","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("42","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("43","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("44","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("45","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("46","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("47","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("48","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("49","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("50","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("51","1467574697","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("52","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("53","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("54","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("55","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("56","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("57","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("58","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("59","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("60","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("61","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("62","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("63","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("64","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("65","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("66","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("67","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("68","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("69","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("70","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("71","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("72","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("73","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("74","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("75","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("76","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("77","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("78","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("79","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("80","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("81","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("82","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("83","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("84","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("85","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("86","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("87","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("88","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("89","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("90","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("91","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("92","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("93","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("94","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("95","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("96","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("97","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("98","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("99","1467574698","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("31","1467575387","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1467575389","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("78","1467575418","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("97","1467575423","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("97","1467575424","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("76","1467575431","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("46","1467575432","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("46","1467575436","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("88","1467575747","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467575751","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467575830","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467575837","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("22","1467575844","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("22","1467575946","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467576008","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("22","1467577187","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467577403","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467577484","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467577609","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("32","1467577760","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1467577875","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1467577968","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("37","1467578132","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1467578445","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1467578460","2","at_modification","at_add_file : apache_pb2.png","");
INSERT INTO teampass_log_items VALUES("392689171","1467578544","2","at_modification","at_add_file : apache_pb.gif","");
INSERT INTO teampass_log_items VALUES("363452148","1467578972","2","at_modification","at_add_file : apache_pb.gif","");
INSERT INTO teampass_log_items VALUES("363452148","1467578972","2","at_modification","at_add_file : apache_pb.png","");
INSERT INTO teampass_log_items VALUES("363452148","1467578972","2","at_modification","at_add_file : apache_pb2.gif","");
INSERT INTO teampass_log_items VALUES("100","1467578978","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("100","1467578978","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467663490","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("30","1467663502","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1467663539","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("30","1467663544","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1467663573","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1467663587","2","at_modification","at_add_file : apache_pb.png","");
INSERT INTO teampass_log_items VALUES("32","1467663705","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("32","1467663718","2","at_modification","at_add_file : apache_pb.gif","");
INSERT INTO teampass_log_items VALUES("32","1467664416","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("30","1467664418","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("30","1467664432","2","at_modification","at_add_file : apache_pb.png","");
INSERT INTO teampass_log_items VALUES("30","1467664432","2","at_modification","at_add_file : apache_pb2.gif","");
INSERT INTO teampass_log_items VALUES("30","1467664432","2","at_modification","at_add_file : apache_pb2.png","");
INSERT INTO teampass_log_items VALUES("22","1467664871","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("22","1467664886","2","at_modification","at_add_file : 2014_11_06_19_18_40_collaborative_passwords_manager.png","");
INSERT INTO teampass_log_items VALUES("22","1467664886","2","at_modification","at_add_file : 2014_11_25_20_37_36_flyer.pub_publisher.png","");
INSERT INTO teampass_log_items VALUES("22","1467664887","2","at_modification","at_add_file : 2014_12_30_08_33_43_vag_technique.fr.png","");
INSERT INTO teampass_log_items VALUES("22","1467664887","2","at_modification","at_add_file : 2015_01_25_11_27_32_collaborative_passwords_manager.png","");
INSERT INTO teampass_log_items VALUES("22","1467664901","2","at_modification","at_pw :4a8bfc0aaf7c87e1193ab7b9ceb4e4ba","1c44f3ba809f34356a257601139053fc");
INSERT INTO teampass_log_items VALUES("2","1467750247","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1467750249","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467750249","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467750261","2","at_modification","at_login :  => yop","");
INSERT INTO teampass_log_items VALUES("2","1467750261","2","at_modification","at_email :  => sdq@sd.net","");
INSERT INTO teampass_log_items VALUES("100","1467750264","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467750264","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467750941","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751084","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751107","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751144","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751165","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751199","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751286","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751326","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751360","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751685","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751808","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467751853","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467752109","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1467921337","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("100","1467921338","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467921346","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("5","1467921360","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("30","1467921370","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("45","1467923380","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467999069","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1467999097","5","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999106","5","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999112","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999142","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999489","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999546","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999590","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999763","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999774","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999833","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999852","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1467999996","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1468000141","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1468000247","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1468000269","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("13","1468000297","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("13","1468000319","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("13","1468000542","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("13","1468000569","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468000596","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("32","1468000607","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("11","1468000642","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1468000648","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("6","1468000655","5","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468349985","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("101","1468349985","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468350028","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468350143","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("22","1468350191","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468350206","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468350226","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468350299","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468350332","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("29","1468350361","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("31","1468350388","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("32","1468350566","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("32","1468350586","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("37","1468350589","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("37","1468350589","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468350848","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468350877","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468351320","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1468351491","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468504463","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468504464","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468663449","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468663450","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("12","1468674770","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("18","1468674772","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("5","1468674780","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("5","1468674782","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("102","1468767316","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("102","1468767316","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("102","1468767318","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468767323","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("102","1468767325","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("102","1468767327","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468767368","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468767368","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("102","1468767379","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("102","1468767381","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468859981","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1468860284","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468860291","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468860416","2","at_modification","at_pw :baf22c2082863148984247cb6b2adb75","ba7c12f793436bae724e20e0b3bb12ee");
INSERT INTO teampass_log_items VALUES("100","1468860419","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468860419","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468862155","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468862411","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468862466","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468862594","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468863156","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468863763","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1468867745","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("100","1468868683","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1468868685","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1468868693","2","at_modification","at_pw :ab0dc61f0e821103794a9d8634a5bdfe","cd2f5ee16ca0e46e418e3c5f142a3536");
INSERT INTO teampass_log_items VALUES("100","1468868694","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1468868695","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("3","1468868696","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468959741","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468960096","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468960820","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1468960822","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469044102","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469044431","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469044488","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469044551","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("103","1469044556","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("103","1469044556","2","at_copy","","");
INSERT INTO teampass_log_items VALUES("103","1469044556","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469430917","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469430918","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469430935","2","at_modification","at_pw :be657d09a6f3481c5c8a93092b403c0d","6cec63115c06b0ca98a24582a13d2eda");
INSERT INTO teampass_log_items VALUES("102","1469430937","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469430938","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469430939","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469430964","2","at_modification","at_pw :3175d73cbb048c52390989c4c9af7e8f","bef42505124db11f746fa529d7e65277");
INSERT INTO teampass_log_items VALUES("102","1469430966","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469430967","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469430968","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469431341","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431341","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431341","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431341","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431341","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431366","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431366","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431366","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431366","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431366","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431384","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431384","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431384","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431384","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469431384","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469441400","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469441401","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469442119","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469442329","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469442434","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469442435","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469442533","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469442535","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("101","1469450745","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469450746","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("101","1469450748","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("102","1469450752","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("102","1469450754","2","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("102","1469450755","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("102","1469450755","2","at_password_copied","","");
INSERT INTO teampass_log_items VALUES("104","1469787322","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("105","1469787322","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("106","1469787322","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("2","1469787960","5","at_shown","","");
INSERT INTO teampass_log_items VALUES("2","1469787961","5","at_password_shown","","");
INSERT INTO teampass_log_items VALUES("107","1470378334","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("107","1470378334","2","at_shown","","");
INSERT INTO teampass_log_items VALUES("108","1470378356","2","at_creation","","");
INSERT INTO teampass_log_items VALUES("108","1470378357","2","at_shown","","");



DROP TABLE teampass_log_system;

CREATE TABLE `teampass_log_system` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `date` varchar(30) NOT NULL,
  `label` text NOT NULL,
  `qui` varchar(30) NOT NULL,
  `field_1` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=62 DEFAULT CHARSET=utf8;

INSERT INTO teampass_log_system VALUES("1","error","1467046138","Query: INSERT INTO `teampass_misc` (`valeur`,`type`,`intitule`) VALUES (NULL, \'admin\', NULL)<br />Error: Le champ \'valeur\' ne peut être vide (null)<br />@ /teampass/sources/admin.queries.php","1","");
INSERT INTO teampass_log_system VALUES("2","user_mngt","1467046276","at_user_added","1","2");
INSERT INTO teampass_log_system VALUES("3","user_mngt","1467047404","at_user_added","1","3");
INSERT INTO teampass_log_system VALUES("4","error","1467047425","Query: INSERT INTO `teampass_users` (`login`,`name`,`lastname`,`pw`,`email`,`admin`,`gestionnaire`,`read_only`,`personal_folder`,`user_language`,`fonction_id`,`groupes_interdits`,`groupes_visibles`,`isAdministratedByRole`) VALUES (\'UG2\', \'gab\', \'riel\', \'$2y$13$831b27363aad6c66ecbaeONe2TTSXsuhn1rKifn594hUtgvXpf/3a\', \'nils@teampass.net\', \'0\', \'0\', \'0\', \'1\', \'english\', \'0\', \'0\', \'0\', \'null\')<br />Error: Incorrect integer value: \'null\' for column \'isAdministratedByRole\' at row 1<br />@ /teampass/sources/users.queries.php","1","");
INSERT INTO teampass_log_system VALUES("5","user_mngt","1467047443","at_user_added","1","4");
INSERT INTO teampass_log_system VALUES("6","user_mngt","1467047462","at_user_added","1","5");
INSERT INTO teampass_log_system VALUES("7","user_mngt","1467047513","at_user_initial_pwd_changed","5","5");
INSERT INTO teampass_log_system VALUES("8","user_mngt","1467047560","at_user_initial_pwd_changed","3","3");
INSERT INTO teampass_log_system VALUES("9","user_mngt","1467130953","at_user_initial_pwd_changed","2","2");
INSERT INTO teampass_log_system VALUES("10","error","1467466402","Query: INSERT INTO `teampass_files` (`id_item`,`name`,`size`,`extension`,`type`,`file`) VALUES (\'1\', \'3008_compresseur.docx\', 13418, \'docx\', \'application/vnd.openxmlformats-officedocument.wordprocessingml.document\', \'4f82a511be24a38d8f2c77c6444dd76a\')<br />Error: Data too long for column \'type\' at row 1<br />@ ","2","");
INSERT INTO teampass_log_system VALUES("11","error","1467466862","Query: INSERT INTO `teampass_files` (`id_item`,`name`,`size`,`extension`,`type`,`file`) VALUES (\'1\', \'3008_compresseur.docx\', 13418, \'docx\', \'application/vnd.openxmlformats-officedocument.wordprocessingml.document\', \'c7c184ebb799dfba677355b49c1d8505\')<br />Error: Data too long for column \'type\' at row 1<br />@ ","2","");
INSERT INTO teampass_log_system VALUES("12","error","1467466959","Query: INSERT INTO `teampass_files` (`id_item`,`name`,`size`,`extension`,`type`,`file`) VALUES (\'1\', \'a_vendre_maison_ancienne_entierement_renovee.docx\', 224472, \'docx\', \'application/vnd.openxmlformats-officedocument.wordprocessingml.document\', \'661b49c868c7dc4b0dc54ce5d45bcdb8\')<br />Error: Data too long for column \'type\' at row 1<br />@ ","2","");
INSERT INTO teampass_log_system VALUES("13","error","1467574698","Query: INSERT INTO `teampass_items` (`label`,`description`,`pw`,`pw_iv`,`url`,`id_tree`,`login`,`anyone_can_modify`) VALUES (\'http://cpanel.000a.biz/index.php?token2=7748247&QWEFJEergrykr5eth340yiwfqwimf2wt9oj45yh9eg3eyuuuu=VG1wak5FNVVhek5PYkRscFRVUkJkMWxSUFQwPQ==&qwefnwergierngiehgqweerghmeoigfmqwmqWFQEFGWRHgiqwdfqnmqwefnwqfpp=VDBSa2JHTnVWa0k9&wefihwrgiewhqhwidfqwhefiwehgiwhgqwifhwhgiwrhgierhgweirgqwpkrw4jgwiefjwnifweg=6479697%20target%20=\', \'cpassman_cpanel.000a.biz\', \'652719708fbe08d349458a8173c01d92\', \'73f9685666a4296a0dcdd1220b648078\', \'\', \'2\', \'a000b_6795876\', 0)<br />Error: Data too long for column \'label\' at row 1<br />@ ","2","");
INSERT INTO teampass_log_system VALUES("14","failed_auth","1467999082","user_not_exists","::1","");
INSERT INTO teampass_log_system VALUES("15","user_mngt","1468349888","at_user_pwd_changed","2","2");
INSERT INTO teampass_log_system VALUES("16","failed_auth","1468349900","user_password_not_correct","::1","");
INSERT INTO teampass_log_system VALUES("17","failed_auth","1468349992","user_not_exists","::1","");
INSERT INTO teampass_log_system VALUES("18","failed_auth","1468438956","user_password_not_correct","::1","");
INSERT INTO teampass_log_system VALUES("19","admin_action","1468438971","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("20","failed_auth","1468440652","user_password_not_correct","::1","");
INSERT INTO teampass_log_system VALUES("21","failed_auth","1468440675","user_password_not_correct","::1","");
INSERT INTO teampass_log_system VALUES("22","user_mngt","1468440716","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("23","failed_auth","1468440762","user_password_not_correct","::1","");
INSERT INTO teampass_log_system VALUES("24","failed_auth","1468504193","user_password_not_correct","::1","");
INSERT INTO teampass_log_system VALUES("25","user_mngt","1468504238","at_user_pwd_changed","1","1");
INSERT INTO teampass_log_system VALUES("26","failed_auth","1468663856","user_not_exists","::1","");
INSERT INTO teampass_log_system VALUES("27","error","1469044113","Query: INSERT INTO `teampass_items` (`label`) VALUES (\'duplicate\')<br />Error: Field \'description\' doesn\'t have a default value<br />@ ","2","");
INSERT INTO teampass_log_system VALUES("28","error","1469044442","Query: INSERT INTO `teampass_items` (`label`) VALUES (\'duplicate\')<br />Error: Field \'description\' doesn\'t have a default value<br />@ ","2","");
INSERT INTO teampass_log_system VALUES("29","error","1469044495","Query: INSERT INTO `teampass_items` (`label`) VALUES (\'duplicate\')<br />Error: Field \'pw\' doesn\'t have a default value<br />@ ","2","");
INSERT INTO teampass_log_system VALUES("30","admin_action","1469431544","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("31","error","1469431629","Query: INSERT INTO teampass_cache VALUES(\"1\",\"TestItem\",NULL,NULL,\"467\",\"0\",NULL,\"blah\",NULL,\"2\",\"0\",\"1467131369\");<br />Error: Le champ \'description\' ne peut être vide (null)<br />@ ","1","");
INSERT INTO teampass_log_system VALUES("32","admin_action","1469432318","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("33","admin_action","1469432524","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("34","admin_action","1469432654","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("35","admin_action","1469433232","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("36","admin_action","1469433312","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("37","admin_action","1469433437","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("38","admin_action","1469433541","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("39","admin_action","1469433571","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("40","admin_action","1469433614","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("41","admin_action","1469433644","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("42","admin_action","1469434843","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("43","admin_action","1469434932","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("44","admin_action","1469435056","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("45","admin_action","1469435170","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("46","error","1469436150","Query: SHOW COLUMNS FROM table_name = \'teampass_api\'<br />Error: Erreur de syntaxe près de \'= \'teampass_api\'\' à la ligne 1<br />@ ","1","");
INSERT INTO teampass_log_system VALUES("47","error","1469436197","Query: SHOW COLUMNS FROM \'teampass_api\'<br />Error: Erreur de syntaxe près de \'\'teampass_api\'\' à la ligne 1<br />@ ","1","");
INSERT INTO teampass_log_system VALUES("48","error","1469436441","Query: SHOW COLUMNS FROM \'teampass_api\'<br />Error: Erreur de syntaxe près de \'\'teampass_api\'\' à la ligne 1<br />@ ","1","");
INSERT INTO teampass_log_system VALUES("49","admin_action","1469436671","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("50","admin_action","1469436740","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("51","admin_action","1469438034","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("52","admin_action","1469438173","dataBase backup","1","");
INSERT INTO teampass_log_system VALUES("53","failed_auth","1469787938","user_not_exists","::1","");
INSERT INTO teampass_log_system VALUES("54","failed_auth","1469787941","user_not_exists","::1","");
INSERT INTO teampass_log_system VALUES("55","error","1469864278","Query: UPDATE `teampass_users` SET `usertimezone`=\'Europe/Paris\' WHERE id = 2<br />Error: Champ \'usertimezone\' inconnu dans field list<br />@ ","2","");
INSERT INTO teampass_log_system VALUES("56","user_mngt","1469864640","at_user_new_usertimezone:2","2","usertimezone_2");
INSERT INTO teampass_log_system VALUES("57","user_mngt","1469864780","at_user_new_usertimezone:2","2","usertimezone_2");
INSERT INTO teampass_log_system VALUES("58","user_mngt","1469864854","at_user_new_usertimezone:2","2","usertimezone_2");
INSERT INTO teampass_log_system VALUES("59","user_mngt","1469865128","at_user_new_usertimezone:2","2","usertimezone_2");
INSERT INTO teampass_log_system VALUES("60","failed_auth","1470378242","user_password_not_correct","::1","");
INSERT INTO teampass_log_system VALUES("61","admin_action","1470382191","dataBase backup","1","");



DROP TABLE teampass_misc;

CREATE TABLE `teampass_misc` (
  `type` varchar(50) NOT NULL,
  `intitule` varchar(100) NOT NULL,
  `valeur` varchar(100) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO teampass_misc VALUES("admin","max_latest_items","10");
INSERT INTO teampass_misc VALUES("admin","enable_favourites","1");
INSERT INTO teampass_misc VALUES("admin","show_last_items","1");
INSERT INTO teampass_misc VALUES("admin","enable_pf_feature","1");
INSERT INTO teampass_misc VALUES("admin","log_connections","0");
INSERT INTO teampass_misc VALUES("admin","log_accessed","1");
INSERT INTO teampass_misc VALUES("admin","time_format","H:i:s");
INSERT INTO teampass_misc VALUES("admin","date_format","d/m/Y");
INSERT INTO teampass_misc VALUES("admin","duplicate_folder","0");
INSERT INTO teampass_misc VALUES("admin","item_duplicate_in_same_folder","0");
INSERT INTO teampass_misc VALUES("admin","duplicate_item","0");
INSERT INTO teampass_misc VALUES("admin","number_of_used_pw","3");
INSERT INTO teampass_misc VALUES("admin","manager_edit","1");
INSERT INTO teampass_misc VALUES("admin","cpassman_dir","D:/wamp64/www/teampass");
INSERT INTO teampass_misc VALUES("admin","cpassman_url","http://localhost/teampass");
INSERT INTO teampass_misc VALUES("admin","favicon","http://localhost/teampass/favico.ico");
INSERT INTO teampass_misc VALUES("admin","path_to_upload_folder","D:/wamp64/www/teampass/upload");
INSERT INTO teampass_misc VALUES("admin","url_to_upload_folder","http://localhost/teampass/upload");
INSERT INTO teampass_misc VALUES("admin","path_to_files_folder","D:/wamp64/www/teampass/files");
INSERT INTO teampass_misc VALUES("admin","url_to_files_folder","http://localhost/teampass/files");
INSERT INTO teampass_misc VALUES("admin","activate_expiration","0");
INSERT INTO teampass_misc VALUES("admin","pw_life_duration","0");
INSERT INTO teampass_misc VALUES("admin","maintenance_mode","0");
INSERT INTO teampass_misc VALUES("admin","enable_sts","0");
INSERT INTO teampass_misc VALUES("admin","encryptClientServer","0");
INSERT INTO teampass_misc VALUES("admin","cpassman_version","2.1.26");
INSERT INTO teampass_misc VALUES("admin","ldap_mode","0");
INSERT INTO teampass_misc VALUES("admin","ldap_type","0");
INSERT INTO teampass_misc VALUES("admin","ldap_suffix","0");
INSERT INTO teampass_misc VALUES("admin","ldap_domain_dn","0");
INSERT INTO teampass_misc VALUES("admin","ldap_domain_controler","0");
INSERT INTO teampass_misc VALUES("admin","ldap_user_attribute","0");
INSERT INTO teampass_misc VALUES("admin","ldap_ssl","0");
INSERT INTO teampass_misc VALUES("admin","ldap_tls","0");
INSERT INTO teampass_misc VALUES("admin","ldap_elusers","0");
INSERT INTO teampass_misc VALUES("admin","ldap_search_base","0");
INSERT INTO teampass_misc VALUES("admin","richtext","0");
INSERT INTO teampass_misc VALUES("admin","allow_print","1");
INSERT INTO teampass_misc VALUES("admin","roles_allowed_to_print","0");
INSERT INTO teampass_misc VALUES("admin","show_description","1");
INSERT INTO teampass_misc VALUES("admin","anyone_can_modify","0");
INSERT INTO teampass_misc VALUES("admin","anyone_can_modify_bydefault","0");
INSERT INTO teampass_misc VALUES("admin","nb_bad_authentication","0");
INSERT INTO teampass_misc VALUES("admin","utf8_enabled","1");
INSERT INTO teampass_misc VALUES("admin","restricted_to","0");
INSERT INTO teampass_misc VALUES("admin","restricted_to_roles","0");
INSERT INTO teampass_misc VALUES("admin","enable_send_email_on_user_login","0");
INSERT INTO teampass_misc VALUES("admin","enable_user_can_create_folders","0");
INSERT INTO teampass_misc VALUES("admin","insert_manual_entry_item_history","0");
INSERT INTO teampass_misc VALUES("admin","enable_kb","1");
INSERT INTO teampass_misc VALUES("admin","enable_email_notification_on_item_shown","0");
INSERT INTO teampass_misc VALUES("admin","enable_email_notification_on_user_pw_change","0");
INSERT INTO teampass_misc VALUES("admin","custom_logo","");
INSERT INTO teampass_misc VALUES("admin","custom_login_text","");
INSERT INTO teampass_misc VALUES("admin","default_language","english");
INSERT INTO teampass_misc VALUES("admin","send_stats","0");
INSERT INTO teampass_misc VALUES("admin","get_tp_info","1");
INSERT INTO teampass_misc VALUES("admin","send_mail_on_user_login","0");
INSERT INTO teampass_misc VALUES("cron","sending_emails","0");
INSERT INTO teampass_misc VALUES("admin","nb_items_by_query","auto");
INSERT INTO teampass_misc VALUES("admin","enable_delete_after_consultation","0");
INSERT INTO teampass_misc VALUES("admin","enable_personal_saltkey_cookie","0");
INSERT INTO teampass_misc VALUES("admin","personal_saltkey_cookie_duration","31");
INSERT INTO teampass_misc VALUES("admin","email_smtp_server","smtp.1und1.de");
INSERT INTO teampass_misc VALUES("admin","email_smtp_auth","1");
INSERT INTO teampass_misc VALUES("admin","email_auth_username","nils@laumaille.fr");
INSERT INTO teampass_misc VALUES("admin","email_auth_pwd","Mar#rom08");
INSERT INTO teampass_misc VALUES("admin","email_port","587");
INSERT INTO teampass_misc VALUES("admin","email_security","tls");
INSERT INTO teampass_misc VALUES("admin","email_server_url","http://localhost/teampass");
INSERT INTO teampass_misc VALUES("admin","email_from","nils@teampass.net");
INSERT INTO teampass_misc VALUES("admin","email_from_name","test");
INSERT INTO teampass_misc VALUES("admin","pwd_maximum_length","40");
INSERT INTO teampass_misc VALUES("admin","2factors_authentication","0");
INSERT INTO teampass_misc VALUES("admin","delay_item_edition","0");
INSERT INTO teampass_misc VALUES("admin","allow_import","1");
INSERT INTO teampass_misc VALUES("admin","proxy_ip","");
INSERT INTO teampass_misc VALUES("admin","proxy_port","");
INSERT INTO teampass_misc VALUES("admin","upload_maxfilesize","10mb");
INSERT INTO teampass_misc VALUES("admin","upload_docext","doc,docx,dotx,xls,xlsx,xltx,rtf,csv,txt,pdf,ppt,pptx,pot,dotx,xltx");
INSERT INTO teampass_misc VALUES("admin","upload_imagesext","jpg,jpeg,gif,png");
INSERT INTO teampass_misc VALUES("admin","upload_pkgext","7z,rar,tar,zip");
INSERT INTO teampass_misc VALUES("admin","upload_otherext","sql,xml");
INSERT INTO teampass_misc VALUES("admin","upload_imageresize_options","1");
INSERT INTO teampass_misc VALUES("admin","upload_imageresize_width","800");
INSERT INTO teampass_misc VALUES("admin","upload_imageresize_height","600");
INSERT INTO teampass_misc VALUES("admin","upload_imageresize_quality","90");
INSERT INTO teampass_misc VALUES("admin","use_md5_password_as_salt","0");
INSERT INTO teampass_misc VALUES("admin","ga_website_name","TeamPass for ChangeMe");
INSERT INTO teampass_misc VALUES("admin","api","0");
INSERT INTO teampass_misc VALUES("admin","subfolder_rights_as_parent","0");
INSERT INTO teampass_misc VALUES("admin","show_only_accessible_folders","0");
INSERT INTO teampass_misc VALUES("admin","enable_suggestion","1");
INSERT INTO teampass_misc VALUES("admin","otv_expiration_period","7");
INSERT INTO teampass_misc VALUES("admin","default_session_expiration_time","60");
INSERT INTO teampass_misc VALUES("admin","duo","0");
INSERT INTO teampass_misc VALUES("admin","enable_server_password_change","1");
INSERT INTO teampass_misc VALUES("admin","ldap_object_class","0");
INSERT INTO teampass_misc VALUES("complex","1","50");
INSERT INTO teampass_misc VALUES("complex","2","50");
INSERT INTO teampass_misc VALUES("complex","3","25");
INSERT INTO teampass_misc VALUES("complex","7","0");
INSERT INTO teampass_misc VALUES("complex","8","0");
INSERT INTO teampass_misc VALUES("complex","9","0");
INSERT INTO teampass_misc VALUES("complex","10","0");
INSERT INTO teampass_misc VALUES("complex","11","0");
INSERT INTO teampass_misc VALUES("complex","12","0");
INSERT INTO teampass_misc VALUES("complex","13","0");
INSERT INTO teampass_misc VALUES("complex","14","0");
INSERT INTO teampass_misc VALUES("complex","15","0");
INSERT INTO teampass_misc VALUES("admin","syslog_enable","1");
INSERT INTO teampass_misc VALUES("admin","syslog_host","localhost");
INSERT INTO teampass_misc VALUES("admin","syslog_port","514");
INSERT INTO teampass_misc VALUES("admin","menu_type","context");
INSERT INTO teampass_misc VALUES("admin","bck_script_path","/backups");
INSERT INTO teampass_misc VALUES("admin","bck_script_filename","bck_cpassman");
INSERT INTO teampass_misc VALUES("admin","ldap_object_class","0");
INSERT INTO teampass_misc VALUES("admin","timezone","Europe/Paris");
INSERT INTO teampass_misc VALUES("complex","40","0");
INSERT INTO teampass_misc VALUES("complex","41","0");



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
) ENGINE=MyISAM AUTO_INCREMENT=42 DEFAULT CHARSET=utf8;

INSERT INTO teampass_nested_tree VALUES("1","0","F1","59","60","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("2","0","F2","61","62","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("3","0","F3","63","82","1","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("4","0","3","45","46","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("5","0","4","47","48","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("6","0","5","49","50","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("7","3","import","64","81","2","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("8","7","General","67","68","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("9","7","Windows","79","80","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("10","7","Network","73","74","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("11","7","Internet","71","72","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("12","7","eMail","65","66","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("13","7","Homebanking","69","70","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("14","7","Recycle Bin","75","76","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("15","7","Tools","77","78","3","0","0","0","0");
INSERT INTO teampass_nested_tree VALUES("16","0","6","51","52","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("17","0","7","53","54","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("18","0","8","55","56","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("19","0","9","57","58","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("20","0","10","3","4","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("21","0","11","5","6","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("22","0","12","7","8","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("23","0","13","9","10","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("24","0","14","11","12","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("25","0","15","13","14","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("26","0","16","15","16","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("27","0","17","17","18","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("28","0","18","19","20","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("29","0","19","21","22","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("30","0","20","29","30","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("31","0","21","31","32","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("32","0","22","33","34","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("33","0","23","35","36","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("34","0","24","37","38","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("35","0","25","39","40","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("36","0","26","41","42","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("37","0","27","43","44","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("38","0","1","1","2","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("39","0","2","23","28","1","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("40","39","U1_2","26","27","2","0","0","1","0");
INSERT INTO teampass_nested_tree VALUES("41","39","U1_1","24","25","2","0","0","1","0");



DROP TABLE teampass_otv;

CREATE TABLE `teampass_otv` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `timestamp` text NOT NULL,
  `code` varchar(100) NOT NULL,
  `item_id` int(12) NOT NULL,
  `originator` tinyint(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=utf8;

INSERT INTO teampass_otv VALUES("23","1468504638","Ohth3ooPahg4wieGhah9cheiyoh7ECho","101","2");



DROP TABLE teampass_restriction_to_roles;

CREATE TABLE `teampass_restriction_to_roles` (
  `role_id` int(12) DEFAULT NULL,
  `item_id` int(12) DEFAULT NULL,
  KEY `role_id_idx` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_rights;

CREATE TABLE `teampass_rights` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `tree_id` int(12) NOT NULL,
  `fonction_id` int(12) NOT NULL,
  `authorized` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_roles_title;

CREATE TABLE `teampass_roles_title` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `allow_pw_change` tinyint(1) NOT NULL DEFAULT '0',
  `complexity` int(5) NOT NULL DEFAULT '0',
  `creator_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

INSERT INTO teampass_roles_title VALUES("1","G1","0","0","1");
INSERT INTO teampass_roles_title VALUES("2","G2","1","0","1");
INSERT INTO teampass_roles_title VALUES("3","G3","1","0","1");
INSERT INTO teampass_roles_title VALUES("4","IT","0","0","1");



DROP TABLE teampass_roles_values;

CREATE TABLE `teampass_roles_values` (
  `role_id` int(12) NOT NULL,
  `folder_id` int(12) NOT NULL,
  `type` varchar(5) NOT NULL DEFAULT 'R',
  KEY `role_id_idx` (`role_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO teampass_roles_values VALUES("1","1","W");
INSERT INTO teampass_roles_values VALUES("2","2","W");
INSERT INTO teampass_roles_values VALUES("3","3","W");
INSERT INTO teampass_roles_values VALUES("4","1","W");
INSERT INTO teampass_roles_values VALUES("4","2","W");
INSERT INTO teampass_roles_values VALUES("4","3","W");
INSERT INTO teampass_roles_values VALUES("1","3","R");
INSERT INTO teampass_roles_values VALUES("3","7","W");
INSERT INTO teampass_roles_values VALUES("4","7","W");
INSERT INTO teampass_roles_values VALUES("1","7","R");
INSERT INTO teampass_roles_values VALUES("1","8","W");
INSERT INTO teampass_roles_values VALUES("2","8","W");
INSERT INTO teampass_roles_values VALUES("3","8","W");
INSERT INTO teampass_roles_values VALUES("4","8","W");
INSERT INTO teampass_roles_values VALUES("1","9","W");
INSERT INTO teampass_roles_values VALUES("2","9","W");
INSERT INTO teampass_roles_values VALUES("3","9","W");
INSERT INTO teampass_roles_values VALUES("4","9","W");
INSERT INTO teampass_roles_values VALUES("1","10","W");
INSERT INTO teampass_roles_values VALUES("2","10","W");
INSERT INTO teampass_roles_values VALUES("3","10","W");
INSERT INTO teampass_roles_values VALUES("4","10","W");
INSERT INTO teampass_roles_values VALUES("1","11","W");
INSERT INTO teampass_roles_values VALUES("2","11","W");
INSERT INTO teampass_roles_values VALUES("3","11","W");
INSERT INTO teampass_roles_values VALUES("4","11","W");
INSERT INTO teampass_roles_values VALUES("1","12","W");
INSERT INTO teampass_roles_values VALUES("2","12","W");
INSERT INTO teampass_roles_values VALUES("3","12","W");
INSERT INTO teampass_roles_values VALUES("4","12","W");
INSERT INTO teampass_roles_values VALUES("1","13","W");
INSERT INTO teampass_roles_values VALUES("2","13","W");
INSERT INTO teampass_roles_values VALUES("3","13","W");
INSERT INTO teampass_roles_values VALUES("4","13","W");
INSERT INTO teampass_roles_values VALUES("1","14","W");
INSERT INTO teampass_roles_values VALUES("2","14","W");
INSERT INTO teampass_roles_values VALUES("3","14","W");
INSERT INTO teampass_roles_values VALUES("4","14","W");
INSERT INTO teampass_roles_values VALUES("1","15","W");
INSERT INTO teampass_roles_values VALUES("2","15","W");
INSERT INTO teampass_roles_values VALUES("3","15","W");
INSERT INTO teampass_roles_values VALUES("4","15","W");



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
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_tags;

CREATE TABLE `teampass_tags` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `tag` varchar(30) NOT NULL,
  `item_id` int(12) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;




DROP TABLE teampass_tokens;

CREATE TABLE `teampass_tokens` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL,
  `token` varchar(255) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `creation_timestamp` varchar(50) NOT NULL,
  `end_timestamp` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=59 DEFAULT CHARSET=utf8;




DROP TABLE teampass_users;

CREATE TABLE `teampass_users` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `pw` varchar(400) DEFAULT NULL,
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
  `avatar` varchar(255) NOT NULL DEFAULT '',
  `avatar_thumb` varchar(255) NOT NULL DEFAULT '',
  `upgrade_needed` tinyint(1) NOT NULL DEFAULT '0',
  `treeloadstrategy` varchar(30) NOT NULL DEFAULT 'full',
  `can_manage_all_users` tinyint(1) NOT NULL DEFAULT '0',
  `usertimezone` varchar(50) NOT NULL DEFAULT 'not_defined',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=MyISAM AUTO_INCREMENT=28 DEFAULT CHARSET=utf8;

INSERT INTO teampass_users VALUES("1","admin","$2y$10$qykQ3Dh3F.4J5AkpOKn1vu7DXNqjsBa.A6VfWJcex1J2ipjpndbV6","","","Thoo1Ieloo6odieh4kaar2eimahpa3shiePhoon1Ti9ieX0ahy","","","1",";1;2;3;4","","1470381396","0","","","","1","0","0","0","0","1470382247","english","","","1470384996","0","$2y$10$tQkgAes0hscA9zaBZ9smAuuscT0SKED7z4VLMRsHtN0NKH6l7aLLS","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("2","U1","$2y$10$Bn.8.ldMXDE9SMuRKcM6mu5x16OjlS79ld2FKTTM50xOkv4dxDryO","0","","queileelai4Woo9quoo5Laihie5geid1luoFiengahcu5xasoo","1468454400","$2y$10$Ln7D4lMrCvpDj87yS7wzruqfa46l.akV././QYnKHMtBZ7ae5F/2O","0","4","0","1470380720","1","nils@teampass.net","","108;107;103;101;100;2;3;102;5;18","1","0","0","0","0","1470381391","english","Jean","Bon","1470384320","0","$2y$10$hrKGTWft.J..Crv6Ety5U.ZZeNwIZeTPgdhfPDufAIAXBllwkBXPW","","user2b42q7.png","user2b42q7_thumb.png","0","full","0","America/Panama");
INSERT INTO teampass_users VALUES("3","UG1","$2y$10$dPoe0RYaMbnjzhM0jQ5ATOYfYuuvfXQQU.cInY2BL9u/JvoP1PRiC","0","","fah4phua7aivoorai0nahbeeNgaipen4rea8Iz5uw1ACheyeiB","1466985600","","0","1","0","1467047556","0","nils@teampass.net","","","1","0","0","0","0","","english","fre","do","1467051156","0","$2y$10$si14Y2gh6g9H91q0ijEgRuY5MataM5ZMp9s8Jxx3P4lauJ9M9iS2q","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("4","UG2","$2y$13$6cb1b2de14b942f9b060eOw2BJadUtaAvejCorpj1SeC9IVUhrzmy","0","","","","","0","2","0","","0","nils@teampass.net","","","1","0","0","0","0","","english","gab","riel","","0","","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("5","UG3","$2y$10$IN4lTBXIzrahY.cEAYMTaOwceqm3fKFKBl5V7uG2Tos9QmuQmN3p2","0","","EquaFeiKongieNg9eo4ohvei8Shiecaipiefaikeivoof0ciet","1466985600","","0","3","0","1469787955","0","nils@teampass.net","","6;2","1","0","0","0","0","1469787956","english","Mar","Tin","1469791555","4","$2y$10$6m/BwDeWsazcgBMafknMA.hCrzqiRnH2O1BGK.V5a6jMaeRtAyB2e","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("6","U10","$2y$10$m2Dg9qLPOnUhtd3kykVf8uBLvCrBrBlOIaAnYuB1ztJUB8QjddzuS","0","","ohmei7ahQu9uwaebei0eeB9zunahqu2paiz5aequai5oodain3","1468663913","","0","0","0","1468663940","0","","","","1","0","0","0","0","","english","","","1468667540","0","$2y$10$InLY2Cg1W9FQ7Z4GDvjXeuNKc29yECj0Il1vFCyk1Au8hR8IiZNBq","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("7","U11","$2y$10$K3yPDShA3hiFRIQa5XnSyOWnA3og7LETnW0pVrGVMADju4d8grGmW","0","","oFeex9Ienaiwuy7pi2datieng0aweekuChahph1iphohShoh5a","1468663952","","0","0","0","1468664391","0","","","","1","0","0","0","0","","english","","","1468667991","0","$2y$10$t/97x.kdXWdDQyiEy/U8Z.XlHaXSlz5qvXTqmXQjMSF.Ftw7orsnG","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("8","U12","$2y$10$LR/CfIKYFYyzGglTxWb0iu2X2A/SeQIwENgwGmOzBizGVrsjkaBvy","0","","jeizoh5hiehu3phoob8ungix1peid1ooyoor2eij2ee8EejoeC","1468664611","","0","0","0","1468664617","0","","","","1","0","0","0","0","","english","","","1468668217","0","$2y$10$PKn/gLxTjextiBwb4YNi3u8vMcK1xxN.zmHJXwm0/88Qim98vNQsy","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("9","U13","$2y$10$zCmB0cgkXKpphbPT6cN75uBqcDODrt/kVu8bRUnJf4OHUdNZFDpTu","0","","tiex4zo9zi0fou2aix6aileiCi2aidohpush1koh3fede9riB0","1468664728","","0","0","0","1468664735","0","","","","1","0","0","0","0","","english","","","1468668335","0","$2y$10$OxbybGsptbTEfcCOckFEquWqxq9kjS65wKPNAeSyz/x40ARX9Yo2q","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("10","U14","$2y$10$qv/GkV.AG2H1pKrpRK3WiORiUxFQ11JS3YEa7nPxL8evSfnvc8JV6","0","","thaeBa9cuv5bucohboos7kie0Yoo8eizee8Thiekee2ohThoeb","1468665354","","0","0","0","1468665360","0","","","","1","0","0","0","0","","english","","","1468668960","0","$2y$10$cBhEX.Z0r8iwcSN7nOjzI.EI6dEG9MdMN6hmHyuUDDtDxJg7BfBju","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("11","U15","$2y$10$ZKhl1hrKLwexHJ1Mt6I/XeCE6s0ObDRwbxA30ND3tHe52hfZslOsW","0","","bei4eifaMie5ohchoubeigh3si3noopu2hao4ieGhiephu5ohM","1468668979","","0","0","0","1468668987","0","","","","1","0","0","0","0","","english","","","1468672587","0","$2y$10$mPu4z1tQmFJG7yV3rOszcu3jmOqQmcpyUmGqombd0UYxmTfvFp4TK","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("12","U16","$2y$10$ADqCunJBEghBx.4OSbVWEeusntPSZDpHrdko7TO/9qeL5Jt5dMr8W","0","","eichax1phi6gah9riewuwohnem7eing5ainah9Godaezaemahw","1468669454","","0","0","0","1468669468","0","","","","1","0","0","0","0","","english","","","1468673068","0","$2y$10$Yy9AIYN1STMPtjmpAKvXd.xd9A2t0lSRv4Q5xKrVdyN1LrDQ6UWA.","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("13","U17","$2y$10$5nlZUpUMvkgWyZQF2gTENuG3NzyOKsfGvKSL2c0tlVqjaDRcQhDki","0","","heeseedePh4Xee9Ooveihe7paeQuohw7eeSh1vaeMohth6je2e","1468670104","","0","0","0","1468670111","0","","","","1","0","0","0","0","","english","","","1468673711","0","$2y$10$Q7gJYa7Dd8nUN8J.0hmNK.obFGmT5VXqQXxiF2lGfCfMPLdDEjK2O","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("14","U18","$2y$10$hOm34WHDLSJ2p89sIpfcvecQdM9t2WAQwHCmlVrP7twqa4GY152fi","0","","iem0equoodi7ka9iev6yoomaijie8Ixahs7yeiCooquaesh1do","1468670137","","0","0","0","1468670249","0","","","","1","0","0","0","0","","english","","","1468673849","0","$2y$10$Kr3vafypge6b3VJjw1pwz.IEatETnCdZOOw2Dh30HOuLrYelgDS4q","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("15","U19","$2y$10$Qqv1.IOQ.LxnEEFxcEMEpuIuqXj7c8x5mcAY.nUMkN2gdt.KEWNcm","0","","moosh4niaGhoo8quoh3ca2mahR0OodaBeicoo2aghook9toot3","1468670324","","0","0","0","1468670330","0","","","","1","0","0","0","0","","english","","","1468673930","0","$2y$10$pIweg1p4tC9cpxKsCvS2B.QJXdXaQgAOazdzKky/SuK1e1GietyTO","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("16","U20","$2y$10$bH4bJe/gvlyclPKGebm6E.t4Vp4tDn5/S6be2PmWWs6YDYIVoxO4i","0","","hah4ohqueithae5ooleez9ohqu5sheil0Oongahd1iech8eeho","1468670366","","0","0","0","1468670375","0","","","","1","0","0","0","0","","english","","","1468673975","0","$2y$10$gLs9GVrom3zAMiE4f3i.keT.v.uwLwmwpMupSLhT.EEIoEEZkivKS","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("17","U21","$2y$10$MRr7L1QUSBWzxWGVHKL67.S9dmEHxCqdgfiruf0zlOVh3Vjy3JcUa","0","","ca4yoht0Eer5Aezi1doochee3sahsiebi5cahk9beek0ahvuna","1468670405","","0","0","0","1468670411","0","","","","1","0","0","0","0","","english","","","1468674011","0","$2y$10$bfhYCmK0Gpf/NezgZT/EJuRY3eFiyjZiaHEklRN9raSJICDrWN5Jy","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("18","U22","$2y$10$7v/glARQcEGWfucaKUPTJOcjDTUSedS/ddfdzRRxnlrFu8AejN8pu","0","","Iruiph7eime7Aeh0ahxaePh0ehookieCheiWu4ieph6lai7Tho","1468670479","","0","0","0","1468670485","0","","","","1","0","0","0","0","","english","","","1468674085","0","$2y$10$4VfA7vMurtyQo7gJ5dI.yOULATO8QEhOjIJtmYHDrCeSoGrDuKcfO","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("19","U23","$2y$10$rN2ZwxUK3wY3xg9QoaGaHu3dHUwgpJDCkJhh0.giDK2v2qtcI/DoO","0","","theech7angai4ahz2iB2pheishie0ieleez0mieG2oSaigoosh","1468670549","","0","0","0","1468670554","0","","","","1","0","0","0","0","","english","","","1468674154","0","$2y$10$zAW4bSaGbZh21lSF8E/U1upIR3vuX.NXXJYHHFaHPqk9Bb7Oru8N2","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("20","U24","$2y$10$jcHms7.CDJDXDTwIgqvrk.KZUFkNM1jmsum2MSfljjP2XDqIrE4au","0","","neede1Rehoov8Ono7thae4Ri0ievad4eiF8Kie1zaen1ohNood","1468670684","","0","0","0","1468670690","0","","","","1","0","0","0","0","","english","","","1468674290","0","$2y$10$5BNHrip1oyBcg7DMT/0Dn.sMseSqrZIxG5RAq7GGECMzlHhHIdCBO","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("21","U25","$2y$10$Ts.nCGfIlzz3S0leSM47tOiQ/XqUIaKf5Ue2zl2oKq/.wz0RznXdy","0","","ou7reezooj2ve0ahH3leiYunoh7jauZ4ro0ochaef0eeMeer3u","1468671069","","0","0","0","1468671076","0","","","","1","0","0","0","0","","english","","","1468674676","0","$2y$10$5fbrnR0BUwSxvxY6s49rTev/JTE09YlVUdgmHmzuAMD.9MlFJhDte","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("22","U26","$2y$10$08p9zeyIxLXEyztoEzEhn.bf396XszA.LP0i5v/6MRTLZa8ejCui6","0","","fier8hee8jaiyoong7OhNgee1ooh6eaNgoosh8wahGhaiReek2","1468671131","","0","0","0","1468671137","0","","","","1","0","0","0","0","","english","","","1468674737","0","$2y$10$SXfpSq36fVmeyEfPB/PoQeD1jo5P6eFaa1uQTYL.ZPWhcB4WUXkgK","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("23","U27","$2y$10$lvbc2fAykOJNH3KW1rl6JOsfBhRlPZpkl4vWRaBfZXgooE6oagWf.","0","","aighe1aeh9uphi3aiheC6ua6aroh4ahf1thahghe4tieRoh0Ph","1468671228","","0","0","0","1468671236","0","","","","1","0","0","0","0","","english","","","1468674836","0","$2y$10$yev6l.fJ7fBum3YmFE799e1pSQ8y119uHUsFsZdC.zWLooHCyZy7a","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("24","U28","$2y$10$FkVcKIBx15BsF8Jz8tX/RuLgLC4odsMdyc.mqOyyhxsqjlOzwsszS","0","","ieNooyieph4coh4AhL4oojof1oht5fiihuuri9eelis7faej6b","1468671302","","0","0","0","1468671310","0","","","","1","0","0","0","0","","english","","","1468674910","0","$2y$10$CCmmETPfvnqxOwlF1MFtcO58/9kgc.ofzm/iTOHz7JQX.6nRnBLmG","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("25","U29","$2y$10$jf2cte2UccS0ivPnTcEiuOq6IQAOULpL3tdXwNkBLqhtGKvgbvEz6","0","","Ephooch8xoghaiyeeghel0ohpee9phooKie6Fai0aiquu3yohj","1468671371","","0","0","0","1468671377","0","","","","1","0","0","0","0","","english","","","1468674977","0","$2y$10$20gLt7PjLUNmRVTXoG1BQ.9IT42FekkLuc6g3OXn8nzdmc1jUUrlq","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("26","U30","$2y$10$hSenrS77BIxp7igCbMXLRuDRzObg4ulKrse44kYoyqbfcN3TvRTVu","0","","eizieke6cei8cooNg7aeghahxo8joi1aeh0tiyai6eamiey5mo","1468671495","","0","0","0","1468671500","0","","","","1","0","0","0","0","","english","","","1468675100","0","$2y$10$LLT6YuZNEUUg0MyVav3TRuThXTJB.Kq1b6fHKcj7t2EkuYPNgZeAK","","","","1","full","0","not_defined");
INSERT INTO teampass_users VALUES("27","U31","$2y$10$PuAYOqjpZ7EI9Q8vF.JRqOSawhuNkzcGGxVr475Uw2OEkI5NJjw2.","0","","leilooXeeb5arai6aceej6eeseishooJ5jaingoocheilui9sh","1468671645","","0","0","0","1468671651","0","","","","1","0","0","0","0","","english","","","1468675251","0","$2y$10$RgdV3COzX5XlUCQW0D6sJerbrIzJK.OGbAtZhDXmvazyBIzRtfQbC","","","","1","full","0","not_defined");



