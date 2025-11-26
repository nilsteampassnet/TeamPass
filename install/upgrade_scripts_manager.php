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
 * @file      upgrade_scripts_manager.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
set_time_limit(600);

require_once './libs/SecureHandler.php';
require_once '../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();

error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = 'utf8';
$_SESSION['CPM'] = 1;
require_once '../includes/config/include.php';

// Prepare POST variables
$post_file_number = filter_input(INPUT_POST, 'file_number', FILTER_SANITIZE_NUMBER_INT);

$scripts_list = array(
    array('upgrade_run_3.0.0.php', 'user_id'),
    array('upgrade_run_3.0.0_passwords.php', 'user_id'),
    array('upgrade_run_3.0.0_logs.php', 'user_id'),
    array('upgrade_run_3.0.0_fields.php', 'user_id'),
    array('upgrade_run_3.0.0_suggestions.php', 'user_id'),
    array('upgrade_run_3.0.0_files.php', 'user_id'),
    array('upgrade_run_3.0.php', 'user_id'),
    array('upgrade_operations.php', '20230604_1'),
    array('upgrade_operations.php', 'populateItemsTable_CreatedAt'),
    array('upgrade_operations.php', 'populateItemsTable_UpdatedAt'),
    array('upgrade_operations.php', 'populateItemsTable_DeletedAt'),
    array('upgrade_operations.php', '20231017_1'),
    array('upgrade_run_3.1.php', 'user_id'),
    array('upgrade_operations.php', 'clean_duplicate_sharekeys'),
    array('upgrade_run_3.1.5.php', 'user_id'),
    array('upgrade_operations.php', 'Transparent_recovery_migration'),
);
$param = '';

// test if finished
if (intval($post_file_number) >= count($scripts_list)) {
    $finished = 1;
} else {
    $finished = 0;
}
echo '[{"finish":"'.$finished.'", "scriptname":"'.$scripts_list[$post_file_number][0].'", "parameter":"'.$scripts_list[$post_file_number][1].'"}]';
