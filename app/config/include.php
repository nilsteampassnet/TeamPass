<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      include.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

define('TP_VERSION', '3.2.0');
define("UPGRADE_MIN_DATE", "1780331401");
define('TP_VERSION_MINOR', '2');
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
define('NUMBER_ITEMS_IN_BATCH', 1000);
define('WIP', (bool) getenv('TEAMPASS_DEBUG'));
define('UPGRADE_SEND_EMAILS', true);
define('KEY_LENGTH', 16);
define('EDITION_LOCK_PERIOD', 86400);   // Defines the delay for which an item edition lock is active
define('EDITION_LOCK_HEARTBEAT_TIMEOUT', 300);  // Lock expires after 5 minutes without heartbeat renewal
define('LOG_TO_SERVER', (bool) getenv('TEAMPASS_DEBUG'));         // Defines if logs are sent to the server
define('OAUTH2_REDIRECTURI', 'index.php?post_type=oauth2');
define('FORCE_PHPSECLIBV3_MIGRATION', true); // Set to true to force phpseclib v1 to v3 migration on user login

// Ensure root path constants are available when this file is included directly
// (e.g. from CLI scripts). Web entry points define them earlier in public/index.php.
// __DIR__ here is app/config/, so two levels up is the repository root.
if (!defined('TEAMPASS_ROOT')) {
    define('TEAMPASS_ROOT', realpath(__DIR__ . '/../..'));
}
if (!defined('TEAMPASS_APP')) {
    define('TEAMPASS_APP', TEAMPASS_ROOT . '/app');
}
if (!defined('TEAMPASS_STORAGE')) {
    define('TEAMPASS_STORAGE', TEAMPASS_ROOT . '/storage');
}
if (!defined('TEAMPASS_PUBLIC')) {
    define('TEAMPASS_PUBLIC', TEAMPASS_ROOT . '/public');
}
if (!defined('TEAMPASS_SECRETS')) {
    define('TEAMPASS_SECRETS', TEAMPASS_ROOT . '/secrets');
}

// Tasks Handler
define('LOG_TASKS', false); // Can be used in order to log background tasks
define('LOG_TASKS_FILE', TEAMPASS_STORAGE . '/logs/teampass_tasks.log'); // Log file for background tasks. 🫸 Ensure you have the right to write in the log file
define('TASKS_LOCK_FILE', TEAMPASS_STORAGE . '/logs/teampass_background_tasks.lock'); // Lock file to prevent concurrent executions. 🫸 Ensure you have the right to write this file
define('TASKS_TRIGGER_FILE', TEAMPASS_STORAGE . '/logs/teampass_background_tasks.trigger'); // Trigger file to notify running handler of new urgent tasks

// Internal constants
define('ERR_NOT_ALLOWED', '1000');
define('ERR_NOT_EXIST', '1001');
define('ERR_SESS_EXPIRED', '1002');
define('ERR_NO_MCRYPT', '1003');
define('ERR_VALID_SESSION', '1004');
define('OTV_USER_ID', '9999991');
define('TP_USER_ID', '9999997');
define('SSH_USER_ID', '9999998');
define('API_USER_ID', '9999999');
define('DEFUSE_ENCRYPTION', true);
define('TP_ENCRYPTION_NAME', 'teampass_aes');
define('TP_DEFAULT_ICON', 'fa-solid fa-folder');
define('TP_DEFAULT_ICON_SELECTED', 'fa-solid fa-folder-open');
define('TP_PW_STRENGTH_1', 0);
define('TP_PW_STRENGTH_2', 20);
define('TP_PW_STRENGTH_3', 38);
define('TP_PW_STRENGTH_4', 48);
define('TP_PW_STRENGTH_5', 60);
define('MIN_PHP_VERSION', '8.2');
define('MIN_MYSQL_VERSION', '8.0.13');
define('MIN_MARIADB_VERSION', '10.2.1');

// URLs
define('READTHEDOC_URL', 'https://documentation.teampass.net/');
define('DOCUMENTATION_URL', 'https://documentation.teampass.net/');
define('HELP_URL', 'https://github.com/nilsteampassnet/TeamPass/discussions');
define('REDDIT_URL', 'https://www.reddit.com/r/TeamPass/');
define('TEAMPASS_URL', 'https://teampass.net');
define("TEAMPASS_ROOT_PATH", TEAMPASS_ROOT . '/');
define('GITHUB_COMMIT_URL', 'https://github.com/nilsteampassnet/TeamPass/commit/');

// Fontawesome icons
define('FONTAWESOME_URL', 'https://fontawesome.com/search?m=free&o=r');

// Duo
define('DUO_ADMIN_URL_INFO', 'https://duo.com/docs/duoweb#overview');
define('DUO_CALLBACK', 'index.php?post_type=duo');

// Debugging
define('DEBUG', false); // Can be used in order to debug the application
define('MYSQL_LOG', false); // Can be used in order to enable global MySQL log. 🫸 Ensure the mysql user has SUPER privilege set
define('MYSQL_LOG_FILE', '/var/log/teampass_mysql_query.log'); // 🫸 Ensure you have the right to write in the log file
define('DEBUGLDAP', false); // Can be used in order to debug LDAP authentication

// Management Pages
$mngPages = array(
    'admin' => 'admin.php',
    'tasks' => 'tasks.php',
    'options' => 'options.php',
    'statistics' => 'statistics.php',
    '2fa' => '2fa.php',
    'ldap' => 'ldap.php',
    'emails' => 'emails.php',
    'backups' => 'backups.php',
    'api' => 'api.php',
    'fields' => 'fields.php',
    'defect' => 'defect.php',
    'actions' => 'actions.php',
    'uploads' => 'uploads.php',
    'oauth' => 'oauth.php',
    'tools' => 'tools.php',
);

// Utilities Pages
$utilitiesPages = array(
    'utilities.renewal' => 'utilities.renewal.php',
    'utilities.deletion' => 'utilities.deletion.php',
    'utilities.logs' => 'utilities.logs.php',
    'utilities.database' => 'utilities.database.php',
    'utilities.health' => 'utilities.health.php',
);
