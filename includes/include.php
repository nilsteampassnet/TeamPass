<?php
/**
 * @file 		include.php
 * @author		Nils Laumaillé
 * @version 	2.1
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

//DONT'T CHANGE BELOW THIS LINE
global $settings, $languages_list;

$k['version'] = "2.1.1";
$k['tool_name'] = "TeamPass";
$k['jquery-version'] = "1.6.2";
$k['jquery-ui-version'] = "1.8.16";
$k['jquery-ui-theme'] = "overcast";
$k['one_month_seconds'] = 2592000;
$k['image_file_ext'] = array('jpg','gif','png','jpeg','tiff','bmp');
$k['office_file_ext'] = array('xls','xlsx','docx','doc','csv','ppt','pptx');

//Management Pages
$mngPages = array(
    'manage_users' => 'users.php',
    'manage_folders' => 'folders.php',
    'manage_roles' => 'roles.php',
    'manage_views' => 'views.php',
    'manage_main' => 'admin.php',
    'manage_settings' => 'admin.settings.php'
);

/*
//languages
$k['langs'] = array(
	'english'=>'en',
	'french'=>'fr',
	'spanish'=>'es',
	'czech'=>'cs',
	'german'=>'de',
'russian'=>'ru',
'japanese'=>'ja',
'portuguese'=>'pr',
'norwegian'=>'no'
);
*/

?>