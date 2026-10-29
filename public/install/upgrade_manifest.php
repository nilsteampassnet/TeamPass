<?php

declare(strict_types=1);

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
 * Ordered upgrade manifest, shared by the web wizard (upgrade_scripts_manager.php) and the
 * command-line upgrade (app/scripts/upgrade-cli.php), so the two can never run different steps.
 *
 * Each entry is [file, parameter]: 'user_id' for an upgrade_run script, the operation name
 * for upgrade_operations.php. An operation belongs to the upgrade_run script it follows.
 * This file only returns data: requested over HTTP, it outputs and executes nothing.
 *
 * @file      upgrade_manifest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

return [
    ['upgrade_run_3.0.0.php', 'user_id'],
    ['upgrade_run_3.0.0_passwords.php', 'user_id'],
    ['upgrade_run_3.0.0_logs.php', 'user_id'],
    ['upgrade_run_3.0.0_fields.php', 'user_id'],
    ['upgrade_run_3.0.0_suggestions.php', 'user_id'],
    ['upgrade_run_3.0.0_files.php', 'user_id'],
    ['upgrade_run_3.0.php', 'user_id'],
    ['upgrade_operations.php', '20230604_1'],
    ['upgrade_operations.php', 'populateItemsTable_CreatedAt'],
    ['upgrade_operations.php', 'populateItemsTable_UpdatedAt'],
    ['upgrade_operations.php', 'populateItemsTable_DeletedAt'],
    ['upgrade_operations.php', '20231017_1'],
    ['upgrade_run_3.1.php', 'user_id'],
    ['upgrade_operations.php', 'clean_duplicate_sharekeys'],
    ['upgrade_run_3.1.5.php', 'user_id'],
    ['upgrade_operations.php', 'Transparent_recovery_migration'],
    ['upgrade_run_3.1.6.php', 'user_id'],
    ['upgrade_run_3.1.7.php', 'user_id'],
    ['upgrade_operations.php', 'deduplicate_misc'],
    ['upgrade_run_3.2.0.php', 'user_id'],
    ['upgrade_run_3.2.1.php', 'user_id'],
    ['upgrade_run_3.2.2.php', 'user_id'],
    ['upgrade_run_3.2.3.php', 'user_id'],
    ['upgrade_operations.php', 'sanitize_legacy_fa_icons'],
    ['upgrade_operations.php', 'sanitize_directory_user_fields'],
    ['upgrade_operations.php', 'sanitize_imported_folder_titles'],
];
