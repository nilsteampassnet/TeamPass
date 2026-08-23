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

// Global kill switch of the customization layer. Enabled by default: with an
// empty emails_templates table the feature is a no-op anyway, and support can
// set it to 0 to fall back to the shipped strings without losing the templates.
mysqli_query(
    $db_link,
    "INSERT IGNORE INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'emails_templates_enabled', '1')"
);

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
