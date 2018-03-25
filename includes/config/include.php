<?php
/**
 *
 * @file          include.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link
 */
// DONT'T CHANGE BELOW THIS LINE
global $SETTINGS, $languagesList, $SETTINGS_EXT;

$SETTINGS_EXT['version'] = "2.1.27";
$SETTINGS_EXT['version_full'] = $SETTINGS_EXT['version'].".11";
$SETTINGS_EXT['tool_name'] = "TeamPass";
$SETTINGS_EXT['one_day_seconds'] = 86400;
$SETTINGS_EXT['one_week_seconds'] = 604800;
$SETTINGS_EXT['one_month_seconds'] = 2592000;
$SETTINGS_EXT['image_file_ext'] = array('jpg', 'gif', 'png', 'jpeg', 'tiff', 'bmp');
$SETTINGS_EXT['office_file_ext'] = array('xls', 'xlsx', 'docx', 'doc', 'csv', 'ppt', 'pptx');
$SETTINGS_EXT['admin_full_right'] = true;
$SETTINGS_EXT['admin_no_info'] = false;
$SETTINGS_EXT['copyright'] = "2009 - ".date('Y');
$SETTINGS_EXT['allowedTags'] = "<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul><blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>";

define('ERR_NOT_ALLOWED', "1000");
define('ERR_NOT_EXIST', "1001");
define('ERR_SESS_EXPIRED', "1002");
define('ERR_NO_MCRYPT', "1003");
define('ERR_VALID_SESSION', "1004");
define('OTV_USER_ID', "9999991");
define('API_USER_ID', "9999999");
define('DEFUSE_ENCRYPTION', true);

// Management Pages
$mngPages = array(
    'manage_users' => 'users.php',
    'manage_folders' => 'folders.php',
    'manage_roles' => 'roles.php',
    'manage_views' => 'views.php',
    'manage_main' => 'admin.php',
    'manage_settings' => 'admin.settings.php'
);
