<?php
/**
 * @author        Nils Laumaillé <nils@teampass.net>
 *
 * @version       2.1.27
 *
 * @copyright     2009-2019 Nils Laumaillé
 * @license       GNU GPL-3.0
 *
 * @see          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once '../sources/SecureHandler.php';
session_name('teampass_session');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = 'utf8';
$_SESSION['CPM'] = 1;

// Prepare POST variables
$post_file_number = filter_input(INPUT_POST, 'file_number', FILTER_SANITIZE_NUMBER_INT);

$scripts_list = array(
    array('upgrade_run_3.0.0.php', 'user_id'),
    array('upgrade_run_3.0.0_passwords.php', 'user_id'),
    array('upgrade_run_3.0.0_logs.php', 'user_id'),
    array('upgrade_run_3.0.0_fields.php', 'user_id'),
    array('upgrade_run_3.0.0_suggestions.php', 'user_id'),
    array('upgrade_run_3.0.0_files.php', 'user_id'),
    //array('./tools/upgrade.tools.field.php', 'user_id'),
    /*array('upgrade_run_db_original.php', ""),
    array('upgrade_run_2.1.26.php', ""),
    array('upgrade_run_encryption_pwd.php', ""),
    array('upgrade_run_encryption_suggestions.php', ""),
    array('upgrade_run_2.1.27.php', ""),
    array('upgrade_run_defuse_for_pwds.php', ""),
    array('upgrade_run_defuse_for_logs.php', ""),
    array('upgrade_run_defuse_for_categories.php', ""),
    array('upgrade_run_defuse_for_custfields.php', ""),
    array('upgrade_run_defuse_for_files.php', ""),
    array('upgrade_run_defuse_for_files_step2.php', ""),
    array('upgrade_run_final.php', "")*/
);
$param = '';

// test if finished
if (intval($post_file_number) >= count($scripts_list)) {
    $finished = 1;
} else {
    $finished = 0;
}
echo '[{"finish":"'.$finished.'", "scriptname":"'.$scripts_list[$post_file_number][0].'", "parameter":"'.$scripts_list[$post_file_number][1].'"}]';
