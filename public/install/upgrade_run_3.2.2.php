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
 * @file      upgrade_run_3.2.2.php
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

//--->BEGIN 3.2.2

// Add the emails_templates table — administrator customizations of the emails
// subjects and bodies, one row per (language key, language). The table is a diff
// over the shipped language files: empty table means today's behaviour, and
// reverting a template is simply deleting its row. No seeding on purpose.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "emails_templates` (
        `id` INT(12) NOT NULL AUTO_INCREMENT,
        `template_key` VARCHAR(100) NOT NULL COMMENT 'Language key of the customized subject or body',
        `language` VARCHAR(50) NOT NULL COMMENT 'Language name, as in the languages table',
        `content` MEDIUMTEXT NOT NULL,
        `updated_at` INT(12) NULL DEFAULT NULL,
        `updated_by` INT(12) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_key_lang` (`template_key`, `language`),
        KEY `idx_language` (`language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Administrator customizations of the emails subjects and bodies'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating emails_templates table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// LAPR (Linux Account Password Rotation)

// LAPR managed endpoints (enrolled Linux servers reachable over SSH).
// No FK constraints (TeamPass project convention) — referential integrity
// is enforced at application level.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'lapr_endpoints` (
        `id` INT(12) NOT NULL AUTO_INCREMENT,
        `label` VARCHAR(255) NOT NULL,
        `hostname` VARCHAR(255) NOT NULL,
        `port` SMALLINT UNSIGNED NOT NULL DEFAULT 22,
        `ssh_username` VARCHAR(100) NOT NULL,
        `ssh_auth_method` ENUM(\'password\',\'key\') NOT NULL DEFAULT \'password\',
        `ssh_credential_source` INT(12) NULL COMMENT \'teampass_items.id holding the SSH credential (app-level link)\',
        `os_info` TEXT NULL COMMENT \'JSON {os_name, kernel, user_info, is_root}\',
        `capabilities` TEXT NULL COMMENT \'JSON {has_chpasswd, has_passwd, has_sudo}\',
        `ssh_hostkey_fingerprint` VARCHAR(255) NULL,
        `ssh_hostkey_verified` TINYINT(1) NOT NULL DEFAULT 1,
        `status` ENUM(\'active\',\'disabled\',\'error\',\'unreachable\',\'deleted\') NOT NULL DEFAULT \'active\',
        `last_check_at` DATETIME NULL,
        `last_error` TEXT NULL,
        `next_check_at` DATETIME NULL,
        `allowed_by_policy` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(12) NOT NULL,
        `updated_by` INT(12) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_hostname` (`hostname`),
        INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating lapr_endpoints table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// LAPR managed accounts (existing TeamPass items whose password is rotated).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'lapr_accounts` (
        `id` INT(12) NOT NULL AUTO_INCREMENT,
        `endpoint_id` INT(12) NOT NULL,
        `item_id` INT(12) NOT NULL COMMENT \'teampass_items.id — LAPR manages this item password\',
        `username_cache` VARCHAR(100) NOT NULL COMMENT \'copy of item.login at add time\',
        `policy_id` INT(12) NULL,
        `last_rotation_at` DATETIME NULL,
        `last_rotation_status` ENUM(\'success\',\'failure\',\'never\') NOT NULL DEFAULT \'never\',
        `last_rotation_error` TEXT NULL,
        `next_rotation_at` DATETIME NULL,
        `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `retry_at` DATETIME NULL DEFAULT NULL,
        `status` ENUM(\'active\',\'paused\',\'error\',\'deleted\') NOT NULL DEFAULT \'active\',
        `created_by` INT(12) NOT NULL,
        `updated_by` INT(12) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_item_id` (`item_id`),
        INDEX `idx_next_rotation` (`next_rotation_at`, `status`),
        INDEX `idx_endpoint` (`endpoint_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating lapr_accounts table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// LAPR rotation policies (frequency + generated password rules).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'lapr_policies` (
        `id` INT(12) NOT NULL AUTO_INCREMENT,
        `label` VARCHAR(255) NOT NULL,
        `frequency_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
        `password_length` TINYINT UNSIGNED NOT NULL DEFAULT 24,
        `use_uppercase` TINYINT(1) NOT NULL DEFAULT 1,
        `use_lowercase` TINYINT(1) NOT NULL DEFAULT 1,
        `use_digits` TINYINT(1) NOT NULL DEFAULT 1,
        `use_symbols` TINYINT(1) NOT NULL DEFAULT 1,
        `rotate_on_enroll` TINYINT(1) NOT NULL DEFAULT 0,
        `is_preset` TINYINT(1) NOT NULL DEFAULT 0,
        `created_by` INT(12) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_preset` (`is_preset`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating lapr_policies table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// LAPR audit log (action_type as VARCHAR — new event types are added by later
// points without schema change; secrets are never stored here).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'lapr_audit_log` (
        `id` INT(12) NOT NULL AUTO_INCREMENT,
        `action_type` VARCHAR(50) NOT NULL COMMENT \'endpoint_add|endpoint_test|endpoint_edit|endpoint_delete|endpoint_pause|endpoint_resume|rotation|rotation_pause_override|account_add|account_reset|hostkey_mismatch|ssh_credential_sync|rotation_retry_scheduled|rotation_suspended\',
        `endpoint_id` INT(12) NULL,
        `account_id` INT(12) NULL,
        `user_id` INT(12) NOT NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `action_details` TEXT NULL COMMENT \'JSON, never contains secrets\',
        `result` ENUM(\'success\',\'failure\',\'warning\') NOT NULL,
        `error_message` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_endpoint` (`endpoint_id`),
        INDEX `idx_account` (`account_id`),
        INDEX `idx_user` (`user_id`),
        INDEX `idx_action` (`action_type`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating lapr_audit_log table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// LAPR rate limiting (per IP and per hostname on SSH test / endpoint add).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'lapr_rate_limit` (
        `id` INT(12) NOT NULL AUTO_INCREMENT,
        `scope` ENUM(\'ip\',\'hostname\') NOT NULL,
        `scope_value` VARCHAR(255) NOT NULL,
        `attempts` INT(12) NOT NULL DEFAULT 0,
        `window_start` INT(12) NOT NULL,
        `blocked_until` INT(12) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_scope` (`scope`, `scope_value`),
        INDEX `idx_blocked` (`blocked_until`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating lapr_rate_limit table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Per-user LAPR management flag (admin-or-flag permission model).
$laprColumnExists = mysqli_fetch_array(mysqli_query(
    $db_link,
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = '" . $database . "'
     AND TABLE_NAME = '" . $pre . "users'
     AND COLUMN_NAME = 'can_manage_lapr'"
));
if (empty($laprColumnExists[0])) {
    if (mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "users`
         ADD `can_manage_lapr` TINYINT(1) NOT NULL DEFAULT 0
         COMMENT 'LAPR: user can manage endpoints/accounts/policies (0=no, 1=yes)'
         AFTER `gestionnaire`"
    ) === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error adding users.can_manage_lapr: ' . addslashes(mysqli_error($db_link)) . '"}]';
        mysqli_close($db_link);
        exit();
    }
}

// background_tasks(process_type, item_id) index. Every LAPR dedup guard filters
// on process_type (+ item_id for the rotation guard), and the LAPR Health and
// Statistics reports run several counters on process_type alone. Without it each
// of them is a full table scan of a table that grows with every task.
$laprTaskIndexExists = mysqli_fetch_array(mysqli_query(
    $db_link,
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = '" . $database . "'
     AND TABLE_NAME = '" . $pre . "background_tasks'
     AND INDEX_NAME = 'idx_process_item'"
));
if (empty($laprTaskIndexExists[0])) {
    if (mysqli_query(
        $db_link,
        "ALTER TABLE `" . $pre . "background_tasks`
         ADD INDEX `idx_process_item` (`process_type`, `item_id`)"
    ) === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error adding background_tasks.idx_process_item: ' . addslashes(mysqli_error($db_link)) . '"}]';
        mysqli_close($db_link);
        exit();
    }
}

// LAPR settings (type 'admin' — read through ConfigManager, saved by the
// generic save_option_change handler). All disabled by default: the module
// is fully opt-in.
$laprSettings = array(
    array('lapr_enabled', '0'),
    array('lapr_allowlist_enabled', '0'),
    array('lapr_allowlist', ''),
    array('lapr_allow_self_management', '0'),
    array('lapr_ssh_connect_timeout', '10'),
    array('lapr_rate_limit_max_attempts', '5'),
    array('lapr_rate_limit_window_seconds', '60'),
    array('lapr_rate_limit_block_seconds', '300'),
    array('lapr_alert_email_enabled', '0'),
    array('lapr_alert_email_recipient', ''),
    array('lapr_scheduler_enabled', '0'),
    array('lapr_scheduler_interval_minutes', '5'),
    array('lapr_scheduler_next_run_at', '0'),
    // Upgrades must not start new outbound SSH traffic without administrator opt-in.
    array('lapr_endpoint_checks_enabled', '0'),
    array('lapr_endpoint_check_interval_minutes', '1440'),
    array('lapr_max_retries', '3'),
    array('lapr_retry_delay_minutes', '60'),
    array('lapr_audit_retention_days', '365'),
);
foreach ($laprSettings as $setting) {
    mysqli_query(
        $db_link,
        "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`)
         VALUES ('admin', '" . $setting[0] . "', '" . addslashes($setting[1]) . "')"
    );
}

// Seed the 3 read-only preset policies (created_by = TP_USER_ID) when absent.
$laprPresets = array(
    // label, frequency_days, password_length, upper, lower, digits, symbols, rotate_on_enroll
    array('Standard (30 days)', 30, 24, 1, 1, 1, 1, 0),
    array('High Security (7 days)', 7, 32, 1, 1, 1, 1, 0),
    array('Weekly + rotate on enroll', 7, 20, 1, 1, 1, 0, 1),
);
foreach ($laprPresets as $preset) {
    $existing = mysqli_fetch_array(mysqli_query(
        $db_link,
        "SELECT COUNT(*) FROM `" . $pre . "lapr_policies`
         WHERE `label` = '" . addslashes($preset[0]) . "' AND `is_preset` = 1"
    ));
    if (empty($existing[0])) {
        mysqli_query(
            $db_link,
            "INSERT INTO `" . $pre . "lapr_policies`
             (`label`, `frequency_days`, `password_length`, `use_uppercase`, `use_lowercase`,
              `use_digits`, `use_symbols`, `rotate_on_enroll`, `is_preset`, `created_by`)
             VALUES ('" . addslashes($preset[0]) . "', " . (int) $preset[1] . ", " . (int) $preset[2] . ",
              " . (int) $preset[3] . ", " . (int) $preset[4] . ", " . (int) $preset[5] . ",
              " . (int) $preset[6] . ", " . (int) $preset[7] . ", 1, " . (int) TP_USER_ID . ")"
        );
    }
}

// Global kill switch of the customization layer. Enabled by default: with an
// empty emails_templates table the feature is a no-op anyway, and support can
// set it to 0 to fall back to the shipped strings without losing the templates.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'emails_templates_enabled', '1')"
);

// Offline synchronization: give every item a monotonic revision ID.
// Default 0 means "never changed since revision tracking was installed", which is
// a consistent starting point for every client, so no backfill is needed here.
$res = addColumnIfNotExist(
    $pre . 'items',
    'revision',
    "INT UNSIGNED NOT NULL DEFAULT '0'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column revision to items table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Durable timestamp paired with items.revision. Unlike items.updated_at, it changes only
// when a functional content revision is allocated and survives journal pruning.
$res = addColumnIfNotExist(
    $pre . 'items',
    'revision_changed_at',
    'BIGINT UNSIGNED NULL DEFAULT NULL'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error adding column revision_changed_at to items table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Item change journal. Its AUTO_INCREMENT primary key is the globally monotonic
// revision sequence, and its rows are the only tombstone left once an item row is
// hard deleted or leaves the caller's visible folders.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . "items_revisions` (
        `revision` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Globally monotonic change sequence',
        `item_id` INT(12) NOT NULL,
        `folder_id` INT(12) NOT NULL DEFAULT '0' COMMENT 'Folder holding the item at change time',
        `previous_folder_id` INT(12) NOT NULL DEFAULT '0' COMMENT 'Source folder, set on move only',
        `action` VARCHAR(20) NOT NULL COMMENT 'created|updated|deleted|restored|moved|purged',
        `changed_by` INT(12) NOT NULL DEFAULT '0',
        `changed_at` INT(11) NOT NULL,
        PRIMARY KEY (`revision`),
        KEY `idx_items_revisions_item` (`item_id`, `revision`),
        KEY `idx_items_revisions_changed_at` (`changed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Item change journal feeding the offline synchronization delta feed'"
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating items_revisions table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Backfill only when the current item revision still has its exact journal row. Older
// journal entries may already have been pruned; their timestamp is genuinely unknown.
$res = mysqli_query(
    $db_link,
    'UPDATE `' . $pre . 'items` AS i
     INNER JOIN `' . $pre . 'items_revisions` AS r
        ON r.`item_id` = i.`id` AND r.`revision` = i.`revision`
     SET i.`revision_changed_at` = r.`changed_at`
     WHERE i.`revision` > 0 AND i.`revision_changed_at` IS NULL'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error backfilling items.revision_changed_at: ' . addslashes(mysqli_error($db_link)) . '"}]';
    mysqli_close($db_link);
    exit();
}

// Offline synchronization window: how long a device may stay offline and still catch up
// incrementally. A client whose cursor falls outside it rebuilds its cache instead. This is
// not a data retention — no item, password or history depends on it.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'offline_sync_window_days', '90')"
);

// Independent manual overrides for the Health System runtime logs. The
// 'teampass' and 'php_fpm' roles were seeded by upgrade_run_3.1.7.php; an empty
// value keeps auto-detection for that log only.
$healthLogsDefaults = [
    'health_webserver_log_path'        => '',
    'health_webserver_access_log_path' => '',
];
foreach ($healthLogsDefaults as $key => $value) {
    mysqli_query(
        $db_link,
        "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES
        ('admin', '" . mysqli_real_escape_string($db_link, $key) . "',
         '" . mysqli_real_escape_string($db_link, $value) . "')"
    );
}

// Notification idempotency. Event producers can provide a stable dedupe key,
// allowing threshold-based and fan-out notifications to be retried safely.
// NULL keeps the historical append-only behaviour for events without a key.
$notificationsTable = $pre . 'user_notifications';
$dedupeColumnExists = mysqli_fetch_array(mysqli_query(
    $db_link,
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = '" . $database . "'
     AND TABLE_NAME = '" . $notificationsTable . "'
     AND COLUMN_NAME = 'dedupe_key'"
));
if (empty($dedupeColumnExists[0])) {
    if (mysqli_query(
        $db_link,
        "ALTER TABLE `" . $notificationsTable . "`
         ADD `dedupe_key` VARCHAR(120) NULL DEFAULT NULL AFTER `event_type`"
    ) === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error adding user_notifications.dedupe_key: ' . addslashes(mysqli_error($db_link)) . '"}]';
        mysqli_close($db_link);
        exit();
    }
}

$dedupeIndex = mysqli_query(
    $db_link,
    "SHOW INDEX FROM `" . $notificationsTable . "` WHERE key_name = 'uk_user_event_dedupe'"
);
if ($dedupeIndex !== false && mysqli_num_rows($dedupeIndex) === 0) {
    if (mysqli_query(
        $db_link,
        "ALTER TABLE `" . $notificationsTable . "`
         ADD UNIQUE KEY `uk_user_event_dedupe` (`user_id`, `event_type`, `dedupe_key`)"
    ) === false) {
        echo '[{"finish":"1", "msg":"", "error":"Error adding user_notifications dedupe index: ' . addslashes(mysqli_error($db_link)) . '"}]';
        mysqli_close($db_link);
        exit();
    }
}

// Drop the temporary installation table left behind by the installer.
//
// `_install` is created unprefixed by install-steps/run.step3|4 and holds the
// administrator password IN CLEAR TEXT plus the SECUREFILE name. It is only ever
// read by install steps 5 and 6, never by the application or by any upgrade path,
// so removing it here is safe and long overdue: run.step6 tried to drop
// "<prefix>_install", a name that never existed, and the fallback cleanup in
// app/sources/core.php only runs while public/install still exists - which is never
// true in Docker, where the entrypoint deletes that directory at boot.
mysqli_query($db_link, 'DROP TABLE IF EXISTS `_install`');

// Save upgrade timestamp (upsert: always update if exists)
mysqli_query(
    $db_link,
    "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'upgrade_timestamp', " . time() . ")
     ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)"
);

//--->END 3.2.2

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';
