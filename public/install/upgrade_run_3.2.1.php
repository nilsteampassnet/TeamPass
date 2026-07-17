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
 * @file      upgrade_run_3.2.1.php
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

//--->BEGIN 3.2.1

// Phase 2 AES v2 (authenticated GCM) write switch. Disabled by default: when off, new data
// keeps the legacy CBC format. Existing v2-aware read paths can already decrypt v2 data.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'aes_v2_write_enabled', '0')"
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

// Add the rotation_flags table — Leaver / Offboarding Risk view (F3, Enterprise
// governance). Companion table: additive, no ALTER on hot tables.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'rotation_flags` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `item_id` int(12) NOT NULL,
        `flagged_at` int(12) NOT NULL DEFAULT 0,
        `flagged_by` int(12) NOT NULL DEFAULT 0,
        `leaver_id` int(12) NOT NULL DEFAULT 0,
        `reason` varchar(50) NOT NULL DEFAULT \'leaver\',
        `status` varchar(20) NOT NULL DEFAULT \'pending\',
        PRIMARY KEY (`increment_id`),
        UNIQUE KEY `uk_item` (`item_id`),
        KEY `idx_leaver` (`leaver_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating rotation_flags table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// Add the Leaver risk toggles (F3): master switch and the auto-flag-on-disable
// behaviour. Both off by default — admin opt-in.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'leaver_risk_enabled', '0')"
);
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'leaver_risk_auto_flag', '0')"
);

// Add the Compliance reports toggle (F6): admin Reports page (access matrix,
// access changes, posture summary, rotation evidence). Off by default.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'compliance_reports_enabled', '0')"
);

// Add the Rotation tracking toggle (F5): overdue rotations + SLA coverage
// reports on the Reports page, driven by the per-folder renewal_period.
// Off by default.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'rotation_tracking_enabled', '0')"
);

// Add the user_notifications table — in-app Notification Centre (D2, Scale &
// polish). Companion table: additive, no ALTER on hot tables.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'user_notifications` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `user_id` int(12) NOT NULL,
        `created_at` int(12) NOT NULL DEFAULT 0,
        `event_type` varchar(50) NOT NULL,
        `payload` text NULL,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `read_at` int(12) NULL DEFAULT NULL,
        PRIMARY KEY (`increment_id`),
        KEY `idx_user_unread` (`user_id`, `is_read`, `increment_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating user_notifications table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// Add the Notification centre toggle (D2): navbar bell + inbox persisting
// user-target events (scan results, task completions, permission changes).
// Off by default.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'notification_center_enabled', '0')"
);

// Add the Command palette toggle (F15): Ctrl+K keyboard-first global search
// over items, folders and pages. Off by default.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'command_palette_enabled', '0')"
);

// Add the Micro-learning toggle (F11): contextual, dismissible security tips
// at the moment of action + a daily rotation. Off by default.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'micro_learning_enabled', '0')"
);

// Add the access_reviews + access_review_items tables — Access Recertification
// Campaigns (F2, Enterprise governance). Companion tables: additive, no ALTER
// on hot tables. Titles are denormalised on purpose (evidence stability).
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'access_reviews` (
        `id` int(12) NOT NULL AUTO_INCREMENT,
        `label` varchar(255) NOT NULL,
        `folder_scope` int(12) NOT NULL DEFAULT 0,
        `started_by` int(12) NOT NULL DEFAULT 0,
        `started_at` int(12) NOT NULL DEFAULT 0,
        `status` varchar(20) NOT NULL DEFAULT \'open\',
        `closed_by` int(12) NULL DEFAULT NULL,
        `closed_at` int(12) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating access_reviews table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'access_review_items` (
        `id` int(12) NOT NULL AUTO_INCREMENT,
        `review_id` int(12) NOT NULL,
        `role_id` int(12) NOT NULL,
        `role_title` varchar(255) NOT NULL DEFAULT \'\',
        `folder_id` int(12) NOT NULL,
        `folder_title` varchar(500) NOT NULL DEFAULT \'\',
        `access_type` varchar(10) NOT NULL DEFAULT \'\',
        `decision` varchar(20) NOT NULL DEFAULT \'pending\',
        `decided_by` int(12) NULL DEFAULT NULL,
        `decided_at` int(12) NULL DEFAULT NULL,
        `comment` varchar(500) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_review_grant` (`review_id`, `role_id`, `folder_id`),
        KEY `idx_review_decision` (`review_id`, `decision`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating access_review_items table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// Add the Access recertification toggle (F2). Off by default — admin opt-in.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'access_reviews_enabled', '0')"
);

// Add the data_classification table — Data Classification & Ownership (F4,
// Enterprise governance). Companion table: additive, no ALTER on hot tables.
$res = mysqli_query(
    $db_link,
    'CREATE TABLE IF NOT EXISTS `' . $pre . 'data_classification` (
        `increment_id` int(12) NOT NULL AUTO_INCREMENT,
        `item_id` int(12) NOT NULL,
        `level` tinyint(1) NOT NULL DEFAULT 0,
        `owner_id` int(12) NULL DEFAULT NULL,
        `updated_by` int(12) NOT NULL DEFAULT 0,
        `updated_at` int(12) NOT NULL DEFAULT 0,
        PRIMARY KEY (`increment_id`),
        UNIQUE KEY `uk_item` (`item_id`),
        KEY `idx_level` (`level`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);
if ($res === false) {
    echo '[{"finish":"1", "msg":"", "error":"Error creating data_classification table: ' . addslashes(mysqli_error($db_link)) . '"}]';
    exit;
}

// Add the Data classification toggle (F4). Off by default — admin opt-in.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'data_classification_enabled', '0')"
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

// Save upgrade timestamp (upsert: always update if exists)
mysqli_query(
    $db_link,
    "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'upgrade_timestamp', " . time() . ")
     ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)"
);

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS
