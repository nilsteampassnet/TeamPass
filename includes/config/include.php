<?php
/**
 * @file          include.php
 *
 * @author        Nils Laumaillé
 *
 * @version       2.1.27
 *
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 *
 * @see
 */
// DONT'T CHANGE BELOW THIS LINE

define('TP_VERSION', '3.0.0');
define('TP_VERSION_FULL', TP_VERSION.'.0');
define('TP_TOOL_NAME', 'Teampass');
define('TP_ONE_DAY_SECONDS', 86400);
define('TP_ONE_WEEK_SECONDS', 604800);
define('TP_ONE_MONTH_SECONDS', 2592000);
define('TP_IMAGE_FILE_EXT', array('jpg', 'gif', 'png', 'jpeg', 'tiff', 'bmp'));
define('TP_OFFICE_FILE_EXT', array('xls', 'xlsx', 'docx', 'doc', 'csv', 'ppt', 'pptx'));
define('TP_ADMIN_FULL_RIGHT', true);
define('TP_ADMIN_NO_INFO', false);
define('TP_COPYRIGHT', '2009 - '.date('Y'));
define('TP_ALLOWED_TAGS', '<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul><blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>');

define('ERR_NOT_ALLOWED', '1000');
define('ERR_NOT_EXIST', '1001');
define('ERR_SESS_EXPIRED', '1002');
define('ERR_NO_MCRYPT', '1003');
define('ERR_VALID_SESSION', '1004');
define('OTV_USER_ID', '9999991');
define('SSH_USER_ID', '9999998');
define('API_USER_ID', '9999999');
define('DEFUSE_ENCRYPTION', true);

// Management Pages
$mngPages = array(
    /*'users' => 'users.php',
    'folders' => 'folders.php',
    'roles' => 'roles.php',
    'utilities' => 'utilities.php',*/
    'admin' => 'admin.php',
    'options' => 'options.php',
    'statistics' => 'statistics.php',
    '2fa' => '2fa.php',
    'special' => 'special.php',
    'ldap' => 'ldap.php',
    'emails' => 'emails.php',
    'backups' => 'backups.php',
    'api' => 'api.php',
    'fields' => 'fields.php',
    'defect' => 'defect.php',
);

// Utilities Pages
$utilitiesPages = array(
    'renewal' => 'utilities.renewal.php',
    'deletion' => 'utilities.deletion.php',
    'logs' => 'utilities.logs.php',
    'database' => 'utilities.database.php',
);
