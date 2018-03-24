<?php
/**
 * @file          upgrade_run_encryption_suggestions.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2018 Nils Laumaillé
 * @licensing     GNU GPL-3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */


require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

// if already defused then current instance of Teampss has already been updated to 2.1.27
if (isset($_SESSION['tp_defuse_installed']) && $_SESSION['tp_defuse_installed'] === true) {
    echo '[{"finish":"1" , "next":"" , "error":""}]';
    return false;
}


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

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Some init
$next = ($post_nb + $post_start);
$_SESSION['settings']['loaded'] = "";
$dbgDuo = fopen("upgrade.log", "a");
$finish = false;

// Test DB connexion
$pass = defuse_return_decrypted($pass);
if (mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
)
) {
    $db_link = mysqli_connect(
        $server,
        $user,
        $pass,
        $database,
        $port
    );
} else {
    $res = "Impossible to get connected to server. Error is: ".addslashes(mysqli_connect_error());
    echo '[{"finish":"1", "error":"Impossible to get connected to server. Error is: '.addslashes(mysqli_connect_error()).'!"}]';
    mysqli_close($db_link);
    exit();
}

fputs($dbgDuo, (string) "\nStarting suggestion.\n\n");
// decrypt passwords in suggestion table
$resData = mysqli_query(
    $db_link,
    "SELECT id, pw, pw_iv
    FROM ".$pre."suggestion"
);
if (!$resData) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
    exit();
}
while ($record = mysqli_fetch_array($resData)) {
    $tmpData = substr(
        decrypt($record['pw']),
        strlen($record['pw_iv'])
    );
    if (isUTF8($tmpData) && !empty($tmpData)) {
        $encrypt = cryption_phpCrypt(
            $tmpData,
            SALT,
            "",
            "encrypt"
        );

        // store Password
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."suggestion
            SET pw = '".$encrypt['string']."', pw_iv = '".$encrypt['iv']."'
            WHERE id =".$record['id']
        );
        if (!$resData) {
            echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
            exit();
        }
    } else {
        //data is lost ... unknown encryption
    }
}
$finish = 1;

fputs($dbgDuo, (string) "\n\nAll finished.\n");

echo '[{"finish":"'.$finish.'" , "next":"" , "error":""}]';
