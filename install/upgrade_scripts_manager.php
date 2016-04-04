<?php
require_once('../sources/sessions.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

$scripts_list = array(
    'upgrade_run_db_original.php',
    'upgrade_run_encryption_pwd.php',
    'upgrade_run_encryption_suggestions.php'
);
$param = "";

// test if finished
if (intval($_POST['file_number']) >= count($scripts_list)) {
    $finished = 1;
} else {
    $finished = 0;
}
echo '[{"finish":"'.$finished.'", "scriptname":"'.$scripts_list[$_POST['file_number']].'", "parameter":"'.$param.'"}]';