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
 * @file      upgrade_run_3.2.4.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// The recovery CLI supplies an isolated DB connection and the original instance key.
if (PHP_SAPI !== 'cli' || !defined('TEAMPASS_RECOVERY_MIGRATION') || TEAMPASS_RECOVERY_MIGRATION !== true) {
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language();
error_reporting(E_ERROR | E_PARSE);
set_time_limit(600);

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

//include librairies
require_once TEAMPASS_ROOT . '/app/includes/language/english.php';
require_once TEAMPASS_ROOT . '/app/config/include.php';
require_once TEAMPASS_ROOT . '/app/config/settings.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';

// DataBase
// Test DB connexion
$pass = defuse_return_decrypted(DB_PASSWD);
$server = (string) DB_HOST;
$pre = (string) DB_PREFIX;
$database = (string) DB_NAME;
$port = (int) DB_PORT;
$user = (string) DB_USER;

$db_link = mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
);
if ($db_link) {
    $db_link->set_charset(DB_ENCODING);
} else {
    echo '[{"finish":"1", "msg":"", "error":"Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error()) . '!"}]';
    exit();
}

}

//---------------------------------------------------------------------

//--->BEGIN 3.2.4

// Email transport state: additive and idempotent, preserving every existing SMTP value.
$emailTransportDefaults = [
    'email_transport' => 'smtp', 'email_smtp_auth_mode' => '',
    'email_config_revision' => bin2hex(random_bytes(16)),
    'email_microsoft_tenant_id' => '', 'email_microsoft_client_id' => '',
    'email_microsoft_client_secret' => '', 'email_microsoft_mailbox' => '',
    'email_google_client_id' => '', 'email_google_client_secret' => '', 'email_google_sender' => '',
];
foreach ($emailTransportDefaults as $emailSetting => $emailDefault) {
    $emailEncrypted = in_array($emailSetting, ['email_microsoft_client_secret', 'email_google_client_secret'], true) ? 1 : 0;
    $emailSettingEscaped = mysqli_real_escape_string($db_link, $emailSetting);
    $emailDefaultEscaped = mysqli_real_escape_string($db_link, $emailDefault);
    if (mysqli_query($db_link,
        "INSERT IGNORE INTO `" . $pre . "misc` (type, intitule, valeur, is_encrypted)
         VALUES ('admin', '" . $emailSettingEscaped . "', '" . $emailDefaultEscaped . "', " . $emailEncrypted . ")"
    ) === false) {
        throw new RuntimeException('Unable to initialize email transport settings.');
    }
}
if (mysqli_query($db_link,
    "CREATE TABLE IF NOT EXISTS `" . $pre . "email_oauth_state` (
    `cache_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `config_revision` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `access_token` MEDIUMTEXT NOT NULL,
    `refresh_token` MEDIUMTEXT NOT NULL,
    `access_token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `expires_at` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `lease_owner` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
    `lease_until` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`cache_key`),
    KEY `idx_config_revision` (`config_revision`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
) === false) {
    throw new RuntimeException('Unable to initialize email token storage.');
}
ConfigManager::invalidateCache();

// Save upgrade timestamp (upsert: always update if exists)
mysqli_query(
    $db_link,
    "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'upgrade_timestamp', " . time() . ")
     ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)"
);

//--->END 3.2.4

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';
