<?php
/**
 * @file          upgrade_run_defuse_for_logs.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/*
** Upgrade script for release 2.1.27
*/
require_once('../sources/SecureHandler.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';

$_SESSION['settings']['loaded'] = "";

$finish = false;
$next = ($_POST['nb'] + $_POST['start']);

$dbTmp = mysqli_connect(
    $_SESSION['server'],
    $_SESSION['user'],
    $_SESSION['pass'],
    $_SESSION['database'],
    $_SESSION['port']
);


// get total items
$rows = mysqli_query($dbTmp,
    "SELECT * FROM ".$_SESSION['pre']."log_items
    WHERE encryption_type = 'not_set'"
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

$total = mysqli_num_rows($rows);

// loop on items
$rows = mysqli_query($dbTmp,
    "SELECT increment_id, id_item, raison, raison_iv, encryption_type FROM ".$_SESSION['pre']."log_items
    WHERE encryption_type = 'not_set' LIMIT ".$_POST['start'].", ".$_POST['nb']
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

while ($data = mysqli_fetch_array($rows)) {
    // extract encrypted string
    $pwd_tmp = explode(":", $data['raison']);
    
    if ($data['encryption_type'] !== "defuse" && substr($pwd_tmp[1], 0, 3) !== "def" && trim($pwd_tmp[0]) === "at_pw") {

        // decrypt with phpCrypt
        $old_pw = cryption_phpCrypt(
            $pwd_tmp[1],
            $_POST['session_salt'],
            $data['raison_iv'],
            "decrypt"
        );

        // encrypt with Defuse
        $new_pw = cryption(
            $old_pw['string'],
            $_SESSION['new_salt'],
            "encrypt"
        );

        // store Password
        mysqli_query($dbTmp,
            "UPDATE ".$_SESSION['pre']."log_items
            SET raison = 'at_pw :".$new_pw['string']."', raison_iv = '', encryption_type = 'defuse'
            WHERE increment_id = ".$data['increment_id']
        );
    } else if (substr($pwd_tmp[1], 0, 3) === "def" && $data['encryption_type'] !== "defuse") {
        mysqli_query($dbTmp,
            "UPDATE ".$_SESSION['pre']."log_items
            SET encryption_type = 'defuse'
            WHERE increment_id = ".$data['increment_id']
        );
    }
}

if ($next >= $total) {
    $finish = 1;
}


echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';