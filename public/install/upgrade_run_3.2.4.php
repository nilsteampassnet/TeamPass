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

// OAuth2 identity binding: the provider identity (issuer key + subject) an OAuth2 account belongs
// to, so a sign-in is matched on an identifier and no longer on a name claim. NULL = not bound
// yet: an existing OAuth2 account is bound at its next successful sign-in, nothing is backfilled.
// Kept apart from the email block below, whose slice tests evaluate on a synthetic database.
$oauth2IdentityUpgradeFailure = static function (string $message): never {
    if (PHP_SAPI === 'cli' && defined('TEAMPASS_RECOVERY_MIGRATION') && TEAMPASS_RECOVERY_MIGRATION === true) {
        throw new RuntimeException($message);
    }
    echo json_encode([['finish' => '1', 'error' => $message]]);
    exit;
};
try {
    $oauth2IdentityColumn = mysqli_query($db_link, "SHOW COLUMNS FROM `" . $pre . "users` LIKE 'oauth2_identity'");
    if ($oauth2IdentityColumn === false) { $oauth2IdentityUpgradeFailure('Unable to inspect the users table.'); }
    if (mysqli_num_rows($oauth2IdentityColumn) === 0 && mysqli_query($db_link,
        "ALTER TABLE `" . $pre . "users` ADD COLUMN `oauth2_identity` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin
         NULL DEFAULT NULL COMMENT 'OAuth2: sha256(issuer key NUL subject); NULL = not bound yet'"
    ) === false) { $oauth2IdentityUpgradeFailure('Unable to add the OAuth2 identity column.'); }
    mysqli_free_result($oauth2IdentityColumn);
    $oauth2IdentityIndex = mysqli_query($db_link, "SHOW INDEX FROM `" . $pre . "users` WHERE Key_name = 'oauth2_identity'");
    if ($oauth2IdentityIndex === false) { $oauth2IdentityUpgradeFailure('Unable to inspect the users table indexes.'); }
    if (mysqli_num_rows($oauth2IdentityIndex) === 0 && mysqli_query($db_link,
        "ALTER TABLE `" . $pre . "users` ADD UNIQUE KEY `oauth2_identity` (`oauth2_identity`)"
    ) === false) { $oauth2IdentityUpgradeFailure('Unable to add the OAuth2 identity index.'); }
    mysqli_free_result($oauth2IdentityIndex);

    // Until now an OAuth2 sign-in silently took over the local or LDAP account holding the same
    // login. Microsoft Entra ID installations keep that conversion (UPNs are set by an
    // administrator, migrations to Entra rely on it); every other one starts with it disabled.
    // INSERT IGNORE: a replay never overrides the administrator's choice.
    $oauth2EndpointResult = mysqli_query($db_link,
        "SELECT valeur FROM `" . $pre . "misc` WHERE type = 'admin' AND intitule = 'oauth2_client_endpoint'"
    );
    if ($oauth2EndpointResult === false) { $oauth2IdentityUpgradeFailure('Unable to read the OAuth2 settings.'); }
    $oauth2EndpointRow = mysqli_fetch_row($oauth2EndpointResult);
    mysqli_free_result($oauth2EndpointResult);
    $oauth2LinkExisting = is_array($oauth2EndpointRow)
        && str_contains((string) $oauth2EndpointRow[0], 'login.microsoftonline.com') ? '1' : '0';
    if (mysqli_query($db_link,
        "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`)
         VALUES ('admin', 'oauth2_link_existing_accounts', '" . $oauth2LinkExisting . "')"
    ) === false) { $oauth2IdentityUpgradeFailure('Unable to initialize the OAuth2 account linking setting.'); }
} catch (Throwable $error) {
    // Keep a usable trace without disclosing SQL values or driver messages.
    error_log('OAuth2 identity migration failure: ' . get_class($error) . ' at '
        . basename($error->getFile()) . ':' . $error->getLine());
    if (PHP_SAPI === 'cli' && defined('TEAMPASS_RECOVERY_MIGRATION') && TEAMPASS_RECOVERY_MIGRATION === true) {
        throw $error;
    }
    $oauth2IdentityUpgradeFailure('Unable to upgrade the OAuth2 identity storage.');
}

// Email transport state: additive and idempotent, preserving every existing SMTP value.
$emailUpgradeFailure = static function (string $message): never {
    if (PHP_SAPI === 'cli' && defined('TEAMPASS_RECOVERY_MIGRATION') && TEAMPASS_RECOVERY_MIGRATION === true) {
        throw new RuntimeException($message);
    }
    echo json_encode([['finish' => '1', 'error' => $message]]);
    exit;
};
try {
    $emailTransportDefaults = [
        'secure_send_allow_email' => '0',
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
            $emailUpgradeFailure('Unable to initialize email transport settings.');
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
        $emailUpgradeFailure('Unable to initialize email token storage.');
    }
    foreach (['email_attempts' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'next_attempt_at' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0'] as $emailColumn => $emailDefinition) {
        $emailColumnResult = mysqli_query($db_link, "SHOW COLUMNS FROM `" . $pre . "background_tasks` LIKE '" . $emailColumn . "'");
        if ($emailColumnResult === false) { $emailUpgradeFailure('Unable to inspect email queue schema.'); }
        if (mysqli_num_rows($emailColumnResult) === 0 && mysqli_query($db_link,
            "ALTER TABLE `" . $pre . "background_tasks` ADD COLUMN `" . $emailColumn . "` " . $emailDefinition
        ) === false) { $emailUpgradeFailure('Unable to initialize email queue scheduling.'); }
        mysqli_free_result($emailColumnResult);
    }
    $emailIndexResult = mysqli_query($db_link, "SHOW INDEX FROM `" . $pre . "background_tasks` WHERE Key_name = 'idx_email_due'");
    if ($emailIndexResult === false) { $emailUpgradeFailure('Unable to inspect email queue index.'); }
    if (mysqli_num_rows($emailIndexResult) === 0 && mysqli_query($db_link,
        "ALTER TABLE `" . $pre . "background_tasks` ADD INDEX idx_email_due (is_in_progress, next_attempt_at, increment_id)"
    ) === false) { $emailUpgradeFailure('Unable to initialize email queue index.'); }
    mysqli_free_result($emailIndexResult);
    ConfigManager::invalidateCache();

    // Secure Send delivery associations contain no URL, recipient or message content.
    require_once TEAMPASS_ROOT . '/app/sources/secure_send_email.php';
    if (mysqli_query($db_link, secureSendEmailSchemaSql($pre . 'secure_send_email')) === false) {
        $emailUpgradeFailure('Unable to initialize Secure Send email metadata.');
    }

} catch (Throwable $error) {
    // Keep a usable trace without disclosing SQL values or driver messages.
    error_log('Email transport migration failure: ' . get_class($error) . ' at '
        . basename($error->getFile()) . ':' . $error->getLine());
    if (PHP_SAPI === 'cli' && defined('TEAMPASS_RECOVERY_MIGRATION') && TEAMPASS_RECOVERY_MIGRATION === true) {
        throw $error;
    }
    $emailUpgradeFailure('Unable to upgrade email transport storage.');
}

// Secure Send for API clients (browser extension, paired devices, scripts) stays off until an
// administrator opts in; a missing row reads as off too. INSERT IGNORE: a replay never overrides
// the administrator's choice.
if (mysqli_query($db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`)
     VALUES ('admin', 'secure_send_api_enabled', '0')"
) === false) {
    $emailUpgradeFailure('Unable to initialize the Secure Send API setting.');
}

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
