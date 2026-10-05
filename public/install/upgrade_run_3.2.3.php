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
 * @file      upgrade_run_3.2.3.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

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

//---------------------------------------------------------------------

//--->BEGIN 3.2.3

// Passkeys held by the vault (TeamPass acting as a WebAuthn authenticator for third-party
// sites). A credential is attached to an item and inherits its folder, rights and recycle bin.
// The private key is encrypted exactly like an item password (objectKey + per-user sharekeys
// in sharekeys_webauthn) and is never returned by any endpoint. No FK constraints (TeamPass
// project convention).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "webauthn_credentials` (
        `id` INT(12) NOT NULL AUTO_INCREMENT,
        `item_id` INT(12) NOT NULL,
        `credential_id` VARCHAR(255) NOT NULL COMMENT 'base64url, server-generated',
        `rp_id` VARCHAR(255) NOT NULL COMMENT 'Relying party id, e.g. github.com',
        `rp_name` VARCHAR(255) NULL DEFAULT NULL,
        `user_handle` VARCHAR(255) NOT NULL COMMENT 'base64url, relying party user id',
        `user_name` VARCHAR(255) NULL DEFAULT NULL,
        `user_display_name` VARCHAR(255) NULL DEFAULT NULL,
        `algorithm` INT(6) NOT NULL DEFAULT '-7' COMMENT 'COSE algorithm, ES256 only',
        `private_key` TEXT NOT NULL COMMENT 'Encrypted PKCS#8 PEM',
        `private_key_meta` TEXT NULL DEFAULT NULL COMMENT 'Encryption metadata, mirrors items.pw_iv',
        `public_key_cose` TEXT NOT NULL COMMENT 'base64 COSE_Key, not secret',
        `sign_count` INT UNSIGNED NOT NULL DEFAULT '0',
        `discoverable` TINYINT(1) NOT NULL DEFAULT '1',
        `created_at` INT(12) NOT NULL,
        `created_by` INT(12) NOT NULL,
        `last_used_at` INT(12) NULL DEFAULT NULL,
        `last_used_by` INT(12) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_credential_id` (`credential_id`),
        KEY `idx_item` (`item_id`),
        KEY `idx_rp` (`rp_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Passkeys for third-party sites held by the vault'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating webauthn_credentials table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Per-user sharekeys of the vault passkeys, same shape as the other sharekeys tables.
// object_id references webauthn_credentials.id. Rows are always written in v3.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "sharekeys_webauthn` (
        `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
        `object_id` INT(12) NOT NULL,
        `user_id` INT(12) NOT NULL,
        `share_key` TEXT NOT NULL,
        `encryption_version` TINYINT(1) NOT NULL DEFAULT '3' COMMENT '1=phpseclib v1 (SHA-1), 3=phpseclib v3 (SHA-256)',
        PRIMARY KEY (`increment_id`),
        UNIQUE KEY `idx_unique_object_user` (`object_id`, `user_id`),
        KEY `user_id_idx` (`user_id`),
        KEY `encryption_version` (`encryption_version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating sharekeys_webauthn table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Passkeys used to sign in to TeamPass itself (TeamPass acting as a relying party). Only the
// public key is stored. wrapped_private_key is a second wrap of the user's private key, set
// for passwordless sign-in only (key_wrap_mode 1 = PRF, 2 = server-side fallback).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "user_webauthn_credentials` (
        `id` INT(12) NOT NULL AUTO_INCREMENT,
        `user_id` INT(12) NOT NULL,
        `credential_id` VARCHAR(255) NOT NULL COMMENT 'base64url',
        `public_key_cose` TEXT NOT NULL,
        `sign_count` INT UNSIGNED NOT NULL DEFAULT '0',
        `aaguid` VARCHAR(36) NULL DEFAULT NULL,
        `transports` VARCHAR(255) NULL DEFAULT NULL,
        `label` VARCHAR(255) NULL DEFAULT NULL COMMENT 'User-chosen device name',
        `key_wrap_mode` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0=none (second factor), 1=PRF, 2=server',
        `wrapped_private_key` TEXT NULL DEFAULT NULL,
        `wrap_salt` VARCHAR(64) NULL DEFAULT NULL,
        `backup_eligible` TINYINT(1) NOT NULL DEFAULT '0',
        `backup_state` TINYINT(1) NOT NULL DEFAULT '0',
        `created_at` INT(12) NOT NULL,
        `last_used_at` INT(12) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_user_credential_id` (`credential_id`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Passkeys used to sign in to TeamPass'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating user_webauthn_credentials table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Passkey settings (type 'admin'). Every feature is off by default. An empty webauthn_rp_id
// means "derive it from the host of cpassman_url".
$webauthnSettings = [
    'webauthn_provider_enabled'           => '0',
    'webauthn_email_on_add'               => '1',
    'webauthn_login_mode'                 => '0',
    'webauthn_login_require_prf'          => '0',
    'webauthn_passwordless_satisfies_mfa' => '1',
    'webauthn_rp_id'                      => '',
    'webauthn_rp_name'                    => 'TeamPass',
];
foreach ($webauthnSettings as $key => $value) {
    mysqli_query(
        $db_link,
        "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES
        ('admin', '" . mysqli_real_escape_string($db_link, $key) . "',
         '" . mysqli_real_escape_string($db_link, $value) . "')"
    );
}

// Save upgrade timestamp (upsert: always update if exists)
mysqli_query(
    $db_link,
    "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'upgrade_timestamp', " . time() . ")
     ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)"
);

//--->END 3.2.3

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';
