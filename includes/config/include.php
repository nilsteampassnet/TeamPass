<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2022 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   3.0.0.22
 *
 * @see      http://www.teampass.net
 */
define('TP_VERSION', '3.0.0');
define('TP_VERSION_FULL', TP_VERSION.'.22');
define('TP_TOOL_NAME', 'Teampass');
define('TP_ONE_DAY_SECONDS', 86400);
define('TP_ONE_WEEK_SECONDS', 604800);
define('TP_ONE_MONTH_SECONDS', 2592000);
define('TP_IMAGE_FILE_EXT', array('jpg', 'gif', 'png', 'jpeg', 'tiff', 'bmp'));
define('TP_OFFICE_FILE_EXT', array('xls', 'xlsx', 'docx', 'doc', 'csv', 'ppt', 'pptx'));
define('TP_ADMIN_FULL_RIGHT', false);
define('TP_ADMIN_NO_INFO', false);
define('TP_COPYRIGHT', '2009-'.date('Y'));
define('TP_ALLOWED_TAGS', '<b><i><sup><sub><em><strong><u><br><br /><a><strike><ul><blockquote><blockquote><img><li><h1><h2><h3><h4><h5><ol><small><font>');
define('TP_FILE_PREFIX', 'EncryptedFile_');
define('NUMBER_ITEMS_IN_BATCH', 100);

define('ERR_NOT_ALLOWED', '1000');
define('ERR_NOT_EXIST', '1001');
define('ERR_SESS_EXPIRED', '1002');
define('ERR_NO_MCRYPT', '1003');
define('ERR_VALID_SESSION', '1004');
define('OTV_USER_ID', '9999991');
define('SSH_USER_ID', '9999998');
define('API_USER_ID', '9999999');
define('DEFUSE_ENCRYPTION', true);
define('TP_ENCRYPTION_NAME', 'teampass_aes');
define('TP_DEFAULT_ICON', 'fas fa-folder');
define('TP_DEFAULT_ICON_SELECTED', 'fas fa-folder-open');
define('TP_PW_STRENGTH_1', 0);
define('TP_PW_STRENGTH_2', 20);
define('TP_PW_STRENGTH_3', 38);
define('TP_PW_STRENGTH_4', 48);
define('TP_PW_STRENGTH_5', 60);

define('READTHEDOC_URL', 'https://teampass.readthedocs.io/en/latest/');
define('DOCUMENTATION_URL', 'https://documentation.teampass.net/');
define('HELP_URL', 'https://github.com/nilsteampassnet/TeamPass/discussions');
define('REDDIT_URL', 'https://www.reddit.com/r/TeamPass/');
define('TEAMPASS_URL', 'https://teampass.net');
define("TEAMPASS_ROOT_PATH", __DIR__);

// Fontawesome icons
define('FONTAWESOME_URL', 'https://fontawesome.com/search?m=free&o=r');

// Duo
define('DUO_ADMIN_URL_INFO', 'https://duo.com/docs/duoweb#overview');
define('DUO_CALLBACK', 'index.php?post_type=duo');

define('DEBUG', false);
define('DEBUGLDAP', false); //Can be used in order to debug LDAP authentication

define("ADMIN_VISIBLE_OTP_ON_LDAP_IMPORT", true);

// Management Pages
$mngPages = array(
    'admin' => 'admin.php',
    'tasks' => 'tasks.php',
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
    'actions' => 'actions.php',
    'uploads' => 'uploads.php',
);

// Utilities Pages
$utilitiesPages = array(
    'utilities.renewal' => 'utilities.renewal.php',
    'utilities.deletion' => 'utilities.deletion.php',
    'utilities.logs' => 'utilities.logs.php',
    'utilities.database' => 'utilities.database.php',
);
