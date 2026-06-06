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
 * @file      upgrade_run_3.2.0.php
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

// Load libraries
$superGlobal = new SuperGlobal();
$lang = new Language(); 


//---------------------------------------------------------------------

//--->BEGIN 3.2.0

// Ensure item notification subscriptions can be inserted on upgraded instances.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'notification` (
        `increment_id` INT(12) NOT NULL AUTO_INCREMENT,
        `item_id` INT(12) NOT NULL,
        `user_id` INT(12) NOT NULL,
        PRIMARY KEY (`increment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating notification table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

$notificationIncrementColumnResult = mysqli_query(
    $db_link,
    "SHOW COLUMNS FROM `" . $pre . "notification` LIKE 'increment_id'"
);
if ($notificationIncrementColumnResult === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error reading notification increment_id column: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}
$notificationIncrementColumn = mysqli_fetch_assoc($notificationIncrementColumnResult);
if (
    $notificationIncrementColumn !== null
    && $notificationIncrementColumn !== false
    && stripos((string) ($notificationIncrementColumn['Extra'] ?? ''), 'auto_increment') === false
) {
    $res = mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "notification` MODIFY `increment_id` INT(12) NOT NULL AUTO_INCREMENT"
    );
    if ($res === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error updating notification increment_id column: ' . addslashes(mysqli_error($db_link)) . '"}]';
        mysqli_close($db_link);
        exit();
    }
}

// Add server-side AES key column to teampass_api (session_aes_key is no longer stored in JWT)
$res = addColumnIfNotExist(
    $pre . 'api',
    'session_aes_key',
    'VARCHAR(64) NULL DEFAULT NULL'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column session_aes_key to api table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Add enable_local_password_recovery setting, aligned with the legacy visibility flag when present.
$legacyForgotPwdRecovery = mysqli_query(
    $db_link,
    "SELECT `valeur` FROM `" . $pre . "misc` WHERE `type` = 'admin' AND `intitule` = 'disable_show_forgot_pwd_link' LIMIT 1"
);
$enableLocalPasswordRecovery = 1;
if ($legacyForgotPwdRecovery !== false && mysqli_num_rows($legacyForgotPwdRecovery) > 0) {
    $legacyForgotPwdRecoveryRow = mysqli_fetch_assoc($legacyForgotPwdRecovery);
    $enableLocalPasswordRecovery = (int) ($legacyForgotPwdRecoveryRow['valeur'] ?? 0) === 1 ? 0 : 1;
}
mysqli_query($db_link, "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin','enable_local_password_recovery', " . (int) $enableLocalPasswordRecovery . ")");

// Add soft-delete column to kb table
$res = addColumnIfNotExist(
    $pre . 'kb',
    'deleted_at',
    'DATETIME NULL DEFAULT NULL'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column deleted_at to kb table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Add enable_kb setting if not present
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'enable_kb', '0')"
);


// Add HIBP (HaveIBeenPwned) columns to teampass_items
$res = addColumnIfNotExist(
    $pre . 'items',
    'hibp_status',
    'TINYINT(1) NOT NULL DEFAULT 0'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column hibp_status to items table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

$res = addColumnIfNotExist(
    $pre . 'items',
    'hibp_count',
    'INT NOT NULL DEFAULT 0'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column hibp_count to items table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

$res = addColumnIfNotExist(
    $pre . 'items',
    'hibp_checked_at',
    'VARCHAR(30) NULL DEFAULT NULL'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column hibp_checked_at to items table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Add admin settings for HIBP feature (disabled by default)
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'hibp_enabled', '0')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'hibp_check_interval_days', '7')"
);

// Add knowledge base comments support.
$res = addColumnIfNotExist(
    $pre . 'kb',
    'allow_comments',
    'TINYINT(1) NOT NULL DEFAULT 0'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column allow_comments to kb table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "kb_comments` (
            `id` int(12) NOT NULL AUTO_INCREMENT,
            `kb_id` int(12) NOT NULL,
            `content` text NOT NULL,
            `author_id` int(12) NOT NULL,
            `created_at` int(12) NOT NULL DEFAULT 0,
            `updated_at` int(12) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_kb_id` (`kb_id`),
            KEY `idx_author_id` (`author_id`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
);

checkIndexExist(
    $pre . 'kb_comments',
    'idx_kb_id',
    'ADD KEY `idx_kb_id` (`kb_id`)'
);

checkIndexExist(
    $pre . 'kb_comments',
    'idx_author_id',
    'ADD KEY `idx_author_id` (`author_id`)'
);

checkIndexExist(
    $pre . 'kb_comments',
    'idx_created_at',
    'ADD KEY `idx_created_at` (`created_at`)'
);

mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
);

mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb_comments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
);

//---------------------------------------------------------------------

//---< END 3.2.X upgrade steps

// Add index and change created/updated/finished_at type.
try {
    $alter_table_query = "
        ALTER TABLE `" . $pre . "background_tasks_logs`
        ADD INDEX idx_iip_pt_arg(is_in_progress, process_type, arguments(30));
    ";
    mysqli_begin_transaction($db_link);
    mysqli_query($db_link, $alter_table_query);
    mysqli_commit($db_link);
} catch (Exception $e) {
    // Rollback transaction if index already exists.
    mysqli_rollback($db_link);
}

// Performance indexes for folder item listing (fixes 12s load times)
checkIndexExist(
    $pre . 'items',
    'idx_items_tree_inactif_deleted',
    'ADD INDEX idx_items_tree_inactif_deleted (id_tree, inactif, deleted_at)'
);
checkIndexExist(
    $pre . 'restriction_to_roles',
    'idx_restriction_item_id',
    'ADD INDEX idx_restriction_item_id (item_id)'
);
// action/raison are VARCHAR(255) utf8mb4 — use prefix lengths to stay within 3072-byte limit
checkIndexExist(
    $pre . 'log_items',
    'idx_log_items_item_action_raison',
    'ADD INDEX idx_log_items_item_action_raison (id_item, action(30), raison(10))'
);
checkIndexExist(
    $pre . 'files',
    'idx_files_item_confirmed',
    'ADD INDEX idx_files_item_confirmed (id_item, confirmed)'
);

// Migrate path_to_upload_folder and path_to_files_folder to storage/ subdirectories
// if they still point to the old root-level locations ({root}/upload and {root}/files).
$row = mysqli_fetch_assoc(mysqli_query($db_link, "SELECT valeur FROM `" . $pre . "misc` WHERE type='admin' AND intitule='cpassman_dir'"));
if ($row && !empty($row['valeur'])) {
    $cpassmanDir = rtrim((string) $row['valeur'], '/\\');

    $rowUpload = mysqli_fetch_assoc(mysqli_query($db_link, "SELECT valeur FROM `" . $pre . "misc` WHERE type='admin' AND intitule='path_to_upload_folder'"));
    if ($rowUpload && strpos((string) $rowUpload['valeur'], '/storage/upload') === false) {
        mysqli_query($db_link, "UPDATE `" . $pre . "misc` SET valeur='" . addslashes($cpassmanDir . '/storage/upload') . "' WHERE type='admin' AND intitule='path_to_upload_folder'");
    }

    $rowFiles = mysqli_fetch_assoc(mysqli_query($db_link, "SELECT valeur FROM `" . $pre . "misc` WHERE type='admin' AND intitule='path_to_files_folder'"));
    if ($rowFiles && strpos((string) $rowFiles['valeur'], '/storage/files') === false) {
        mysqli_query($db_link, "UPDATE `" . $pre . "misc` SET valeur='" . addslashes($cpassmanDir . '/storage/files') . "' WHERE type='admin' AND intitule='path_to_files_folder'");
    }

    // Scheduled backups now default to storage/backups. Migrate only empty or
    // known legacy root-level backup/files locations; explicit storage/files
    // locations remain accepted by the runtime as legacy-compatible paths.
    $newScheduledBackupDir = $cpassmanDir . '/storage/backups';
    $legacyFilesDir = rtrim(str_replace('\\', '/', $cpassmanDir . '/files'), '/');
    $legacyBackupsDir = rtrim(str_replace('\\', '/', $cpassmanDir . '/backups'), '/');
    $rowScheduled = mysqli_fetch_assoc(mysqli_query($db_link, "SELECT valeur FROM `" . $pre . "misc` WHERE type='settings' AND intitule='bck_scheduled_output_dir'"));
    if ($rowScheduled === null || $rowScheduled === false) {
        mysqli_query($db_link, "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('settings', 'bck_scheduled_output_dir', '" . addslashes($newScheduledBackupDir) . "')");
    } else {
        $scheduledDir = trim((string) ($rowScheduled['valeur'] ?? ''));
        $scheduledDirNormalized = rtrim(str_replace('\\', '/', $scheduledDir), '/');
        $isLegacyScheduledDir = $scheduledDir === ''
            || $scheduledDirNormalized === $legacyFilesDir
            || strpos($scheduledDirNormalized, $legacyFilesDir . '/') === 0
            || $scheduledDirNormalized === $legacyBackupsDir
            || strpos($scheduledDirNormalized, $legacyBackupsDir . '/') === 0;

        if ($isLegacyScheduledDir === true) {
            mysqli_query($db_link, "UPDATE `" . $pre . "misc` SET valeur='" . addslashes($newScheduledBackupDir) . "' WHERE type='settings' AND intitule='bck_scheduled_output_dir'");
        }
    }

    $rowUrl = mysqli_fetch_assoc(mysqli_query($db_link, "SELECT valeur FROM `" . $pre . "misc` WHERE type='admin' AND intitule='url_to_files_folder'"));
    if ($rowUrl && strpos((string) $rowUrl['valeur'], '/storage/files') === false) {
        $rowCpassmanUrl = mysqli_fetch_assoc(mysqli_query($db_link, "SELECT valeur FROM `" . $pre . "misc` WHERE type='admin' AND intitule='cpassman_url'"));
        if ($rowCpassmanUrl && !empty($rowCpassmanUrl['valeur'])) {
            $newUrl = rtrim((string) $rowCpassmanUrl['valeur'], '/') . '/storage/files';
            mysqli_query($db_link, "UPDATE `" . $pre . "misc` SET valeur='" . addslashes($newUrl) . "' WHERE type='admin' AND intitule='url_to_files_folder'");
        }
    }
}

// Add api_cors_origins setting (CORS origin whitelist for API, empty = same-host only)
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'api_cors_origins', '')"
);

// Add PHP-FPM performance settings: CLI binary override (empty = auto-detect)
// and fastcgi_finish_request flush toggle (enabled by default, no-op under mod_php)
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'cli_php_binary_path', '')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'enable_fastcgi_finish_request', '1')"
);

// Add LDAP login group restriction settings
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_allowed_login_group_dn', '')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'ldap_allowed_login_group_mode', 'group')"
);

// Add the api_tokens table used by Personal Access Tokens (OAuth2/SSO API access).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'api_tokens` (
        `id` int(12) NOT NULL AUTO_INCREMENT,
        `user_id` int(12) NOT NULL,
        `token_hash` varchar(64) NOT NULL,
        `wrapped_private_key` text NOT NULL,
        `salt` varchar(64) NOT NULL,
        `label` varchar(255) NULL DEFAULT NULL,
        `created_at` int(12) NOT NULL,
        `expires_at` int(12) NULL DEFAULT NULL,
        `last_used_at` int(12) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token_hash` (`token_hash`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating api_tokens table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// Add OAuth2-for-API toggle (disabled by default; admin opt-in for SSO users API access).
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'oauth2_api_enabled', '0')"
);

// Save upgrade timestamp (upsert: always update if exists)
mysqli_query(
    $db_link,
    "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'upgrade_timestamp', " . time() . ")
     ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)"
);

// Drop obsolete password migration tracking column (added in 3.1.5, no longer read by app)
$columnNeedsPwMigrationExists = mysqli_fetch_array(mysqli_query(
    $db_link,
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = '" . $database . "'
     AND TABLE_NAME = '" . $pre . "users'
     AND COLUMN_NAME = 'needs_password_migration'"
));
if (!empty($columnNeedsPwMigrationExists[0])) {
    mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "users` DROP COLUMN `needs_password_migration`"
    );
}

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS
