<?php
/**
 *
 * @file          include.php
 * @author        Nils Laumaillé
 * @version       2.1.25
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link
 */
// DONT'T CHANGE BELOW THIS LINE
global $settings, $languagesList;

$k['version'] = "2.1.25";
$k['tool_name'] = "TeamPass";
$k['one_month_seconds'] = 2592000;
$k['image_file_ext'] = array('jpg', 'gif', 'png', 'jpeg', 'tiff', 'bmp');
$k['office_file_ext'] = array('xls', 'xlsx', 'docx', 'doc', 'csv', 'ppt', 'pptx');
$k['admin_full_right'] = true;
$k['admin_no_info'] = false;
$k['copyright'] = "2009 - 2015";
$k['allowedTags'] = "<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul><blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>";

@define('ERR_NOT_ALLOWED', "1000");
@define('ERR_NOT_EXIST', "1001");
@define('ERR_SESS_EXPIRED', "1002");
@define('ERR_NO_MCRYPT', "1003");
@define('ERR_VALID_SESSION', "1004");

// Management Pages
$mngPages = array(
    'manage_users' => 'users.php',
    'manage_folders' => 'folders.php',
    'manage_roles' => 'roles.php',
    'manage_views' => 'views.php',
    'manage_main' => 'admin.php',
    'manage_settings' => 'admin.settings.php'
);