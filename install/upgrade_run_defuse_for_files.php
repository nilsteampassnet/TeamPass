<?php
/**
 * @file          upgrade_run_defuse_for_files.php
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
// are files encrypted? get the setting ongoing in teampass
$set = mysqli_fetch_row(mysqli_query($dbTmp,"SELECT valeur FROM ".$_SESSION['pre']."misc WHERE type='admin' AND intitule='enable_attachment_encryption'"));
$enable_attachment_encryption = $set[0];

// if no encryption then stop
if ($enable_attachment_encryption === "0") {
    mysqli_query($dbTmp, "update `".$_SESSION['pre']."files` set status = 'clear' where 1 = 1");
    echo '[{"finish":"1" , "next":"", "error":""}]';
    exit();
}

// get path to upload
$set = mysqli_fetch_row(mysqli_query($dbTmp,"SELECT valeur FROM ".$_SESSION['pre']."misc WHERE type='admin' AND intitule='path_to_upload_folder'"));
$path_to_upload_folder = $set[0];

// get previous saltkey
$set = mysqli_fetch_row(mysqli_query($dbTmp,"SELECT valeur FROM ".$_SESSION['pre']."misc WHERE type='admin' AND intitule='saltkey_ante_2127'"));
$saltkey_ante_2127 = $set[0];


// get total items
$rows = mysqli_query($dbTmp, "SELECT id FROM ".$_SESSION['pre']."files");
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

$total = mysqli_num_rows($rows);

// loop on files
$rows = mysqli_query($dbTmp,
    "SELECT * FROM ".$_SESSION['pre']."files
    LIMIT ".$_POST['start'].", ".$_POST['nb']
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

// Prepare encryption options - with new KEY
if (file_exists(SECUREPATH."/teampass-seckey.txt")) {
    $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");
    $iv = substr(hash("md5", "iv".$ascii_key), 0, 8);
    $key = substr(
        hash("md5", "ssapmeat1".$ascii_key, true), 0, 24);
    $opts_encrypt = array('iv'=>$iv, 'key'=>$key);

    // Prepare encryption options - with old KEY
    $iv = substr(md5("\x1B\x3C\x58".$saltkey_ante_2127, true), 0, 8);
    $key = substr(
        md5("\x2D\xFC\xD8".$saltkey_ante_2127, true).
        md5("\x2D\xFC\xD9".$saltkey_ante_2127, true),
        0,
        24
    );
    $opts_decrypt = array('iv'=>$iv, 'key'=>$key);


    while ($data = mysqli_fetch_array($rows)) {
        if (file_exists($path_to_upload_folder.'/'.$data['file'])) {
            // make a copy of file
            if (!copy(
                    $path_to_upload_folder.'/'.$data['file'],
                    $path_to_upload_folder.'/'.$data['file'].".copy"
            )) {
                $error = "Copy not possible";
                exit;
            } else {
                // do a bck
                copy(
                    $path_to_upload_folder.'/'.$data['file'],
                    $path_to_upload_folder.'/'.$data['file'].".bck"
                );
            }

            // Open the file
            unlink($path_to_upload_folder.'/'.$data['file']);
            $fp = fopen($path_to_upload_folder.'/'.$data['file'].".copy", "rb");
            $out = fopen($path_to_upload_folder.'/'.$data['file'].".tmp", 'wb');

            // decrypt using old
            stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts_decrypt);
            // copy to file
            stream_copy_to_stream($fp, $out);
            // clean
            fclose($fp);
            fclose($out);
            unlink($path_to_upload_folder.'/'.$data['file'].".copy");


            // Now encrypt the file with new saltkey
            $fp = fopen($path_to_upload_folder.'/'.$data['file'].".tmp", "rb");
            $out = fopen($path_to_upload_folder.'/'.$data['file'], 'wb');
            // encrypt using new
            stream_filter_append($out, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts_encrypt);
            // copy to file
            while (($line = fgets($fp)) !== false) {
                fputs($out, $line);
            }

            // clean
            fclose($fp);
            fclose($out);
            unlink($path_to_upload_folder.'/'.$data['file'].".tmp");

            // update table
            mysqli_query($dbTmp, "UPDATE `".$_SESSION['pre']."files`
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