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

// Remove obsolete API key/IP whitelist rows (managed UI tabs were removed; never used by the API).
$res = mysqli_query(
    $db_link,
    "DELETE FROM `" . $pre . "api` WHERE `type` IN ('key', 'ip')"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error purging obsolete api key/ip rows: ' . addslashes(mysqli_error($db_link)) . '"}]';
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

// Allow rich KB articles with embedded images.
$res = mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb` MODIFY COLUMN `description` MEDIUMTEXT NOT NULL"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error updating description column in kb table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Add enable_kb setting if not present
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'enable_kb', '0')"
);

// Allow WebSocket events to target the global knowledge base channel.
$websocketEventsTableResult = mysqli_query(
    $db_link,
    "SHOW TABLES LIKE '" . $pre . "websocket_events'"
);
if ($websocketEventsTableResult !== false && mysqli_num_rows($websocketEventsTableResult) > 0) {
    $res = mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "websocket_events` MODIFY `target_type` ENUM('user', 'folder', 'kb', 'broadcast') NOT NULL COMMENT 'Target type for routing'"
    );
    if ($res === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error updating websocket_events target_type: ' . addslashes(mysqli_error($db_link)) . '"}]';
        mysqli_close($db_link);
        exit();
    }
}

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

// Phase 2 AES v2 (authenticated GCM) write switch. Disabled by default: when off, new data
// keeps the legacy CBC format. Existing v2-aware read paths can already decrypt v2 data.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'aes_v2_write_enabled', '0')"
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

mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "kb_edition` (
            `increment_id` int(12) NOT NULL AUTO_INCREMENT,
            `kb_id` int(12) NOT NULL,
            `user_id` int(12) NOT NULL,
            `timestamp` int(11) NOT NULL,
            KEY `kb_id_idx` (`kb_id`),
            PRIMARY KEY (`increment_id`)
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

checkIndexExist(
    $pre . 'kb_edition',
    'kb_id_idx',
    'ADD KEY `kb_id_idx` (`kb_id`)'
);

mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
);

mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb_comments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
);

mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb_edition` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
);

// Edition-lock timestamps are Unix timestamps: store them as integers.
// MODIFY auto-casts existing numeric-string rows; stale lock rows are transient.
mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "items_edition` MODIFY `timestamp` int(11) NOT NULL;"
);

mysqli_query(
    $db_link,
    "ALTER TABLE `" . $pre . "kb_edition` MODIFY `timestamp` int(11) NOT NULL;"
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

// Enforce a UNIQUE constraint on cache.id so the API cache self-heal insert is
// atomic (closes the duplicate-row race) and lookups by item id use an index.
// Any pre-existing duplicate rows must be removed before the constraint can be
// added, keeping the most recent (highest increment_id) row per item id. The
// de-duplication only runs when the constraint is not present yet, so re-running
// the upgrade does not repeat the self-join DELETE.
$cacheUniqueIndex = mysqli_query(
    $db_link,
    "SHOW INDEX FROM `" . $pre . "cache` WHERE key_name = 'idx_cache_id_unique'"
);
if ($cacheUniqueIndex !== false && mysqli_num_rows($cacheUniqueIndex) === 0) {
    mysqli_query(
        $db_link,
        "DELETE c1 FROM `" . $pre . "cache` c1
         INNER JOIN `" . $pre . "cache` c2
            ON c1.id = c2.id AND c1.increment_id < c2.increment_id"
    );
    checkIndexExist(
        $pre . 'cache',
        'idx_cache_id_unique',
        'ADD UNIQUE KEY idx_cache_id_unique (id)'
    );
}

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

// Add the "extension token for all auth types" toggle (disabled by default).
// When enabled, local/LDAP users (not only OAuth2) can mint and use Personal Access
// Tokens, which powers the browser-extension auto-configuration flow.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'extension_token_all_auth_types', '0')"
);

// Add the api_sessions table — one API session per issued JWT (keyed by the jti
// claim). Enables concurrent API clients per user, per-token revocation and the
// "active API sessions" list in the user profile.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'api_sessions` (
        `id` int(12) NOT NULL AUTO_INCREMENT,
        `user_id` int(12) NOT NULL,
        `jti` varchar(32) NOT NULL,
        `key_tempo` varchar(100) NOT NULL,
        `encrypted_private_key` text NULL,
        `session_aes_key` varchar(64) NULL,
        `user_agent` varchar(255) NOT NULL DEFAULT \'\',
        `created_at` int(12) NOT NULL,
        `expires_at` int(12) NOT NULL,
        `last_used_at` int(12) NULL DEFAULT NULL,
        `revoked_at` int(12) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `jti` (`jti`),
        KEY `user_id` (`user_id`),
        KEY `expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating api_sessions table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// Add the api_rate_limit table — sliding-window request counters (per user and per IP).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'api_rate_limit` (
        `scope_key` varchar(100) NOT NULL,
        `window_start` int(12) NOT NULL,
        `hits` int(12) NOT NULL DEFAULT 1,
        PRIMARY KEY (`scope_key`, `window_start`),
        KEY `window_start` (`window_start`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating api_rate_limit table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// Require HTTPS for the API: enabled on NEW installs, kept DISABLED on upgrades so
// existing HTTP-based integrations keep working (a health-check warning is raised
// instead; the admin can enable it from Settings > API).
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'api_require_https', '0')"
);

// API rate limit (requests per minute, per user and per IP): 120 on NEW installs,
// 0 (disabled) on upgrades so existing heavy API clients are not throttled silently.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'api_rate_limit_per_minute', '0')"
);

// Invalidate API folders cache: cache_tree.folders may hold a truncated list
// (personal subfolders wrongly filtered out before 3.2.0.3). Marking rows as
// invalidated forces a rebuild on the next API request.
mysqli_query(
    $db_link,
    "UPDATE `" . $pre . "cache_tree` SET `invalidated_at` = " . time()
);

// Add the item_health table — per-user security posture flags for the Security
// Posture Dashboard (F1). Companion table: additive, no ALTER on hot tables.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'item_health` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `item_id` int(12) NOT NULL,
        `user_id` int(12) NOT NULL,
        `flag_weak` tinyint(1) NOT NULL DEFAULT 0,
        `flag_reused` tinyint(1) NOT NULL DEFAULT 0,
        `flag_breached` tinyint(1) NOT NULL DEFAULT 0,
        `flag_overdue` tinyint(1) NOT NULL DEFAULT 0,
        `flag_no_expiry` tinyint(1) NOT NULL DEFAULT 0,
        `flag_overshared` tinyint(1) NOT NULL DEFAULT 0,
        `flag_orphaned` tinyint(1) NOT NULL DEFAULT 0,
        `reuse_group` varchar(32) NULL DEFAULT NULL,
        `last_scan_at` int(12) NOT NULL DEFAULT 0,
        PRIMARY KEY (`increment_id`),
        UNIQUE KEY `uk_item_user` (`item_id`, `user_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_reuse_group` (`user_id`, `reuse_group`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating item_health table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// Add the Security Posture Dashboard toggle (disabled by default; admin opt-in)
// and the "over-shared" threshold used by the dashboard scan.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'security_dashboard_enabled', '0')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'security_dashboard_overshared_threshold', '10')"
);

// Add the user_nudges table — per-user email-digest bookkeeping for the
// Proactive Health Nudges (F8). Companion table: additive, no ALTER on hot tables.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'user_nudges` (
        `user_id` int(12) NOT NULL,
        `last_digest_at` int(12) NOT NULL DEFAULT 0,
        `last_score` tinyint unsigned NULL DEFAULT NULL,
        `last_score_delta` smallint NULL DEFAULT NULL,
        `last_score_at` int(12) NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating user_nudges table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// F10 Personal Security Score: add the score-snapshot columns when missing, so an
// install that already created user_nudges for F8 gets the new columns on upgrade.
$nudgesTable = $pre . 'user_nudges';
$nudgesNewColumns = [
    'last_score'       => 'ADD `last_score` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `last_digest_at`',
    'last_score_delta' => 'ADD `last_score_delta` SMALLINT NULL DEFAULT NULL AFTER `last_score`',
    'last_score_at'    => 'ADD `last_score_at` INT(12) NOT NULL DEFAULT 0 AFTER `last_score_delta`',
];
foreach ($nudgesNewColumns as $columnName => $alterClause) {
    $columnExists = mysqli_fetch_array(mysqli_query(
        $db_link,
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = '" . $database . "'
         AND TABLE_NAME = '" . $nudgesTable . "'
         AND COLUMN_NAME = '" . $columnName . "'"
    ));
    if (empty($columnExists[0])) {
        if (mysqli_query($db_link, "ALTER TABLE `" . $nudgesTable . "` " . $alterClause) === false) {
            echo '[{"finish":"1", "msg":"", "error":"Error adding user_nudges.' . $columnName . ': ' . addslashes(mysqli_error($db_link)) . '"}]';
            mysqli_close($db_link);
            exit();
        }
    }
}

// Add the Proactive Health Nudges toggles (F8): master switch, opt-in email
// digest, digest cadence (days) and the stale-scan threshold (days). All off /
// conservative by default — admin opt-in, and gated by security_dashboard_enabled.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'security_nudges_enabled', '0')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'security_nudges_email_enabled', '0')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'security_nudges_email_frequency_days', '7')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'security_nudges_stale_scan_days', '14')"
);

// FUNC-4 — add retry tracking columns to background_subtasks (failed subtasks are re-queued)
$res = addColumnIfNotExist(
    $pre . 'background_subtasks',
    'retry_count',
    'TINYINT(3) UNSIGNED NOT NULL DEFAULT 0'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column retry_count to background_subtasks table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}
$res = addColumnIfNotExist(
    $pre . 'background_subtasks',
    'max_retries',
    'TINYINT(3) UNSIGNED NOT NULL DEFAULT 3'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column max_retries to background_subtasks table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Save upgrade timestamp (upsert: always update if exists)
mysqli_query(
    $db_link,
    "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'upgrade_timestamp', " . time() . ")
     ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)"
);

// Add the import_tracking table — per-operation follow-up of item imports
// (format, status, item/folder counts, timestamps). Powers the "import
// follow-up" panel and history on the Import page.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'import_tracking` (
        `id` int(12) NOT NULL AUTO_INCREMENT,
        `operation_id` int(12) NOT NULL,
        `user_id` int(12) NOT NULL,
        `format` varchar(20) NOT NULL,
        `status` varchar(20) NOT NULL DEFAULT \'analyzing\',
        `total_items` int(12) NOT NULL DEFAULT 0,
        `imported_items` int(12) NOT NULL DEFAULT 0,
        `failed_items` int(12) NOT NULL DEFAULT 0,
        `folders_count` int(12) NOT NULL DEFAULT 0,
        `message` text NULL,
        `started_at` int(12) NOT NULL,
        `finished_at` int(12) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `operation_id` (`operation_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating import_tracking table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

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

// F14 Secure Send: elevate the OTV engine.
// The otv table must accept ad-hoc note sends (no item) and the optional
// passphrase-protected key model. All changes are additive and idempotent.
$otvTable = $pre . 'otv';

// item_id -> nullable (note sends are not bound to an item)
$itemIdColumn = mysqli_fetch_assoc(mysqli_query(
    $db_link,
    "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = '" . $database . "'
     AND TABLE_NAME = '" . $otvTable . "'
     AND COLUMN_NAME = 'item_id'"
));
if ($itemIdColumn !== null && strtoupper((string) ($itemIdColumn['IS_NULLABLE'] ?? '')) === 'NO') {
    if (mysqli_query($db_link, "ALTER TABLE `" . $otvTable . "` MODIFY `item_id` INT(12) NULL DEFAULT NULL") === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error making otv.item_id nullable: ' . addslashes(mysqli_error($db_link)) . '"}]';
        mysqli_close($db_link);
        exit();
    }
}

// New columns (added only when missing)
$otvNewColumns = [
    'send_type'       => "ADD `send_type` VARCHAR(10) NOT NULL DEFAULT 'item' AFTER `item_id`",
    'protected_key'   => "ADD `protected_key` TEXT NULL DEFAULT NULL AFTER `encrypted`",
    'has_passphrase'  => "ADD `has_passphrase` TINYINT(1) NOT NULL DEFAULT 0 AFTER `protected_key`",
    'failed_attempts' => "ADD `failed_attempts` INT(10) NOT NULL DEFAULT 0 AFTER `has_passphrase`",
];
foreach ($otvNewColumns as $columnName => $alterClause) {
    $columnExists = mysqli_fetch_array(mysqli_query(
        $db_link,
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = '" . $database . "'
         AND TABLE_NAME = '" . $otvTable . "'
         AND COLUMN_NAME = '" . $columnName . "'"
    ));
    if (empty($columnExists[0])) {
        if (mysqli_query($db_link, "ALTER TABLE `" . $otvTable . "` " . $alterClause) === false) {
            echo '[{"finish":"1", "msg":"", "error":"Error adding otv.' . $columnName . ': ' . addslashes(mysqli_error($db_link)) . '"}]';
            mysqli_close($db_link);
            exit();
        }
    }
}

// Seed Secure Send settings (idempotent)
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'secure_send_allow_notes', '0')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'secure_send_max_views', '5')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'secure_send_require_passphrase', '0')"
);

// F12 First-run onboarding wizard: per-user completion flag (added only when missing).
// Existing users are marked as completed so the wizard never auto-pops after an upgrade;
// only accounts created afterwards (DEFAULT 0) trigger it on their first connection.
$onboardingColumnExists = mysqli_fetch_array(mysqli_query(
    $db_link,
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = '" . $database . "'
     AND TABLE_NAME = '" . $pre . "users'
     AND COLUMN_NAME = 'onboarding_completed'"
));
if (empty($onboardingColumnExists[0])) {
    if (mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "users`
         ADD `onboarding_completed` TINYINT(1) NOT NULL DEFAULT 0
         COMMENT 'First-run onboarding wizard completed (0=no, 1=done/skipped)'"
    ) === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error adding users.onboarding_completed: ' . addslashes(mysqli_error($db_link)) . '"}]';
        mysqli_close($db_link);
        exit();
    }
    // Backfill existing users once (runs only when the column was just created).
    mysqli_query($db_link, "UPDATE `" . $pre . "users` SET `onboarding_completed` = 1");
}

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS
