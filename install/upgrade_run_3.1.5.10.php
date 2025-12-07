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
 * @file      upgrade_run_3.1.5.10.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
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
$_SESSION['CPM'] = 1;

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

//include librairies
require_once '../includes/language/english.php';
require_once '../includes/config/include.php';

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Database prefix
$dbPrefix = DB_PREFIX;

// Execute migration
if ($post_nb === '1') {
    // Step 1: Add needs_password_migration column to users table
    try {
        DB::queryRaw(
            "ALTER TABLE `" . $dbPrefix . "users`
            ADD COLUMN IF NOT EXISTS `needs_password_migration` TINYINT(1) DEFAULT 0
            AFTER `pw`"
        );

        // Mark all local users as needing migration
        // Users authenticated via LDAP or OAuth2 don't need migration
        DB::queryRaw(
            "UPDATE `" . $dbPrefix . "users`
            SET `needs_password_migration` = 1
            WHERE (`auth_type` = 'local' OR `auth_type` IS NULL OR `auth_type` = '')"
        );

        echo '[{"finish":"1", "msg":"", "error":""}]';
    } catch (Exception $e) {
        echo '[{"finish":"1", "msg":"", "error":"Error adding needs_password_migration column: ' . addslashes($e->getMessage()) . '"}]';
    }
}
