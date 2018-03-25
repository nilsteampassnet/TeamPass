<?php
/**
 * @file          upgrade_run_defuse_for_categories.php
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

// Prepare POST variables
$post_nb = filter_input(INPUT_POST, 'nb', FILTER_SANITIZE_NUMBER_INT);
$post_start = filter_input(INPUT_POST, 'start', FILTER_SANITIZE_NUMBER_INT);

// Some init
$_SESSION['settings']['loaded'] = "";
$finish = false;
$next = ($post_nb + $post_start);

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

// Get old saltkey from saltkey_ante_2127
$db_sk = mysqli_fetch_array(
    mysqli_query(
        $db_link,
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
    $db_link,
    "SELECT * FROM ".$pre."categories_items"
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
    exit();
}

$total = mysqli_num_rows($rows);

// loop on items
$rows = mysqli_query(
    $db_link,
    "SELECT id, data, data_iv, encryption_type FROM ".$pre."categories_items
    LIMIT ".$post_start.", ".$post_nb
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
    exit();
}

while ($data = mysqli_fetch_array($rows)) {
    if ($data['encryption_type'] !== "defuse" && substr($data['data'], 0, 3) !== "def") {
        // decrypt with phpCrypt
        $old_pw = cryption_phpCrypt(
            $data['data'],
            $old_saltkey,
            $data['data_iv'],
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
            $db_link,
            "UPDATE ".$pre."categories_items
            SET data = '".$new_pw['string']."', data_iv = '', encryption_type = 'defuse'
            WHERE id = ".$data['id']
        );
    } elseif ($data['encryption_type'] !== "defuse" && substr($data['data'], 0, 3) === "def") {
        mysqli_query(
            $db_link,
            "UPDATE ".$pre."categories_items
            SET data_iv = '', encryption_type = 'defuse'
            WHERE id = ".$data['id']
        );
    }
}

if ($next >= $total) {
    $finish = 1;
}


echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';
