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
 * @file      migrate_users_to_v3.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

set_time_limit(600);


require_once './libs/SecureHandler.php';
session_name('teampass_session');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = 'utf8';
$_SESSION['CPM'] = 1;

// Prepare POST variables
$post_file_number = filter_input(INPUT_POST, 'file_number', FILTER_SANITIZE_NUMBER_INT);

$scripts_list = array(
    array('upgrade_run_3.0.0_users.php', 'user_id'),
);
$param = '';

// test if finished
if (intval($post_file_number) >= count($scripts_list)) {
    $finished = 1;
} else {
    $finished = 0;
}
echo '[{"finish":"' . $finished . '", "scriptname":"' . $scripts_list[$post_file_number][0] . '", "parameter":"' . $scripts_list[$post_file_number][1] . '"}]';
