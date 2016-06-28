<?php
/**
 * @file          upgrade_run_encryption_suggestions.php
 * @author        Nils Laumaillé
 * @version       2.1.26
 * @copyright     (c) 2009-2016 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */


require_once('../sources/sessions.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
if (!file_exists("../includes/config/settings.php")) {
    echo 'document.getElementById("res_step1_error").innerHTML = "";';
    echo 'document.getElementById("res_step1_error").innerHTML = '.
        '"File settings.php does not exist in folder includes/! '.
        'If it is an upgrade, it should be there, otherwise select install!";';
    echo 'document.getElementById("loader").style.display = "none";';
    exit;
}
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';

$_SESSION['settings']['loaded'] = "";

$dbgDuo = fopen("upgrade.log", "w");
$finish = false;
$next = ($_POST['nb']+$_POST['start']);

$dbTmp = mysqli_connect(
    $_SESSION['db_host'],
    $_SESSION['db_login'],
    $_SESSION['db_pw'],
    $_SESSION['db_bdd'],
    $_SESSION['db_port']
);

fputs($dbgDuo, "\nStarting suggestion.\n\n");
// decrypt passwords in suggestion table
$resData = mysqli_query($dbTmp,
    "SELECT id, pw, pw_iv
    FROM ".$_SESSION['tbl_prefix']."suggestion"
);
if (!$resData) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}
while ($record = mysqli_fetch_array($resData)) {
    $tmpData = substr(decrypt($record['pw']), strlen($record['pw_iv']));
    if (isUTF8($tmpData ) && !empty($tmpData )) {
        $encrypt = cryption($tmpData , SALT, "", "encrypt");

        // store Password
        mysqli_query($dbTmp,
            "UPDATE ".$_SESSION['tbl_prefix']."suggestion
            SET pw = '".$encrypt['string']."', pw_iv = '".$encrypt['iv']."'
            WHERE id =".$record['id']
        );
        if (!$resData) {
            echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
            exit();
        }
    } else {
        //data is lost ... unknown encryption
    }
}
$finish = 1;

fputs($dbgDuo, "\n\nAll finished.\n");

echo '[{"finish":"'.$finish.'" , "next":"" , "error":""}]';