<?php
/**
 * @file          upgrade_run_defuse_for_files.php
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

// if no encryption then stop
if ($SETTINGS['enable_attachment_encryption'] === "0") {
    mysqli_query($db_link, "update `".$pre."files` set status = 'clear' where 1 = 1");
    echo '[{"finish":"1" , "next":"", "error":""}]';
    exit();
}


// If $SETTINGS['saltkey_ante_2127'] is set to'none', then encryption is done with Defuse, so exit
// Also quit if no new defuse saltkey was generated
if (isset($SETTINGS['files_with_defuse'])) {
    if ($SETTINGS['files_with_defuse'] !== "done") {
        if (isset($_SESSION['tp_defuse_new_key']) && $_SESSION['tp_defuse_new_key'] === false) {
            // update
            mysqli_query(
                $db_link,
                "UPDATE `".$pre."misc`
                SET `valeur` = 'done'
                WHERE type='admin' AND intitule='files_with_defuse'"
            );

            echo '[{"finish":"1" , "next":"", "error":""}]';
            exit();
        }
    } else {
        echo '[{"finish":"1" , "next":"", "error":""}]';
        exit();
    }
}

// get total items
$rows = mysqli_query($db_link, "SELECT id FROM ".$pre."files");
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
    exit();
}

$total = mysqli_num_rows($rows);

// loop on files
$rows = mysqli_query(
    $db_link,
    "SELECT * FROM ".$pre."files
    LIMIT ".$post_start.", ".$post_nb
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($db_link).'"}]';
    exit();
}

// Prepare encryption options - with new KEY
if (file_exists(SECUREPATH."/teampass-seckey.txt")) {
    // Prepare encryption options for Defuse
    $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");
    $iv = substr(hash("md5", "iv".$ascii_key), 0, 8);
    $key = substr(hash("md5", "ssapmeat1".$ascii_key, true), 0, 24);
    $opts_encrypt = array('iv'=>$iv, 'key'=>$key);

    // Prepare encryption options - with old KEY
    if ($SETTINGS['saltkey_ante_2127'] !== "none" && empty($SETTINGS['saltkey_ante_2127']) === false) {
        // Encoding option were set as this in Teampass version < 2.1.27
        $iv = substr(md5("\x1B\x3C\x58".$SETTINGS['saltkey_ante_2127'], true), 0, 8);
        $key = substr(
            md5("\x2D\xFC\xD8".$SETTINGS['saltkey_ante_2127'], true).
            md5("\x2D\xFC\xD9".$SETTINGS['saltkey_ante_2127'], true),
            0,
            24
        );
    } else {
        // Encoding option were set as this in Teampass version = 2.1.27.0
        $iv = substr(md5("\x1B\x3C\x58".$ascii_key, true), 0, 8);
        $key = substr(
            md5("\x2D\xFC\xD8".$ascii_key, true).
            md5("\x2D\xFC\xD9".$ascii_key, true),
            0,
            24
        );
    }
    $opts_decrypt = array('iv'=>$iv, 'key'=>$key);

    while ($data = mysqli_fetch_array($rows)) {
        if (file_exists($SETTINGS['path_to_upload_folder'].'/'.$data['file'])) {
            // Make a copy of file
            if (!copy(
                $SETTINGS['path_to_upload_folder'].'/'.$data['file'],
                $SETTINGS['path_to_upload_folder'].'/'.$data['file'].".copy"
            )) {
                $error = "Copy not possible";
                exit;
            } else {
                // do a bck
                copy(
                    $SETTINGS['path_to_upload_folder'].'/'.$data['file'],
                    $SETTINGS['path_to_upload_folder'].'/'.$data['file'].".bck"
                );
            }

            // Open the file
            unlink($SETTINGS['path_to_upload_folder'].'/'.$data['file']);
            $file_primary = fopen($SETTINGS['path_to_upload_folder'].'/'.$data['file'].".copy", "rb");
            $out = fopen($SETTINGS['path_to_upload_folder'].'/'.$data['file'].".tmp", 'wb');

            // decrypt using old
            stream_filter_append($file_primary, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts_decrypt);
            // copy to file
            stream_copy_to_stream($file_primary, $out);
            // clean
            fclose($file_primary);
            fclose($out);
            unlink($SETTINGS['path_to_upload_folder'].'/'.$data['file'].".copy");


            // Now encrypt the file with new saltkey
            $file_primary = fopen($SETTINGS['path_to_upload_folder'].'/'.$data['file'].".tmp", "rb");
            $out = fopen($SETTINGS['path_to_upload_folder'].'/'.$data['file'], 'wb');
            // encrypt using new
            stream_filter_append($out, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts_encrypt);
            // copy to file
            while (($line = fgets($file_primary)) !== false) {
                fputs($out, (string) $line);
            }

            // clean
            fclose($file_primary);
            fclose($out);
            unlink($SETTINGS['path_to_upload_folder'].'/'.$data['file'].".tmp");

            // update table
            mysqli_query(
                $db_link,
                "UPDATE `".$pre."files`
                SET `status` = 'encrypted'
                WHERE id = '".$data['id']."'"
            );
        }
    }

    if ($next >= $total) {
        $finish = 1;
    }
} else {
    echo '[{"finish":"1" , "error":"'.SECUREPATH.'/teampass-seckey.txt does not exist!"}]';
    exit();
}


echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';
