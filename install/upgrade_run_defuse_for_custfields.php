<?php
/**
 * @file          upgrade_run_defuse_for_custfields.php
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
require_once '../includes/config/tp.config.php';

// Some init
$_SESSION['settings']['loaded'] = "";
$finish = false;
$_POST['nb'] = intval($_POST['nb']);
$_POST['start'] = intval($_POST['start']);
$next = ($_POST['nb'] + $_POST['start']);

// Open DB connection
$dbTmp = mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
);

// Get old saltkey from saltkey_ante_2127
$db_sk = mysqli_fetch_array(
    mysqli_query(
        $dbTmp,
        "SELECT valeur FROM ".$pre."misc
        WHERE type='admin' AND intitule = 'saltkey_ante_2127'"
    )
);
if (isset($db_sk['valeur']) && empty($db_sk['valeur']) === false) {
    $old_saltkey = $db_sk['valeur'];
} else {
    echo '[{"finish":"1" , "error":"Previous Saltkey not in database."}]';
    exit();
}

// Read saltkey
$ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");


// get total items
$rows = mysqli_query(
    $dbTmp,
    "SELECT * FROM ".$pre."log_items
    WHERE raison_iv IS NOT NULL"
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

$total = mysqli_num_rows($rows);

// loop on items
$rows = mysqli_query(
    $dbTmp,
    "SELECT increment_id, id_item, raison, raison_iv, encryption_type FROM ".$pre."log_items
    WHERE raison_iv IS NOT NULL
    LIMIT ".$_POST['start'].", ".$_POST['nb']
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

while ($data = mysqli_fetch_array($rows)) {
    if ($data['encryption_type'] !== "defuse") {
        $tmp = explode('at_pw :', $data['raison']);
        if (substr($tmp[0], 0, 3) !== "def") {
            // decrypt with phpCrypt
            $old_pw = cryption_phpCrypt(
                $tmp[0],
                $old_saltkey,
                $data['raison_iv'],
                "decrypt"
            );

            // encrypt with Defuse
            $new_pw = cryption(
                $old_pw['string'],
                $ascii_key,
                "encrypt"
            );

            // store Password
            mysqli_query(
                $dbTmp,
                "UPDATE ".$pre."log_items
                SET raison = 'at_pw :".$new_pw['string']."', raison_iv = '', encryption_type = 'defuse'
                WHERE increment_id = ".$data['increment_id']
            );
        } else {
            mysqli_query(
                $dbTmp,
                "UPDATE ".$pre."log_items
                SET raison_iv = '', encryption_type = 'defuse'
                WHERE increment_id = ".$data['increment_id']
            );
        }
    }
}

if ($next >= $total) {
    $finish = 1;
}


echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';
