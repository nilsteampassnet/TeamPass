<?php
/**
 * @file          upgrade_run_defuse_for_files_step2.php
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

// prepare Encryption class calls
use \Defuse\Crypto\Crypto;
use \Defuse\Crypto\File;
use \Defuse\Crypto\Exception as Ex;

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
$set = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT valeur FROM ".$_SESSION['pre']."misc WHERE type='admin' AND intitule='enable_attachment_encryption'"));
$enable_attachment_encryption = $set[0];

// if no encryption then stop
if ($enable_attachment_encryption === "0") {
    mysqli_query($dbTmp, "update `".$_SESSION['pre']."files` set status = 'clear' where 1 = 1");
    echo '[{"finish":"1" , "next":"", "error":""}]';
    exit();
}

// get path to upload
$set = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT valeur FROM ".$_SESSION['pre']."misc WHERE type='admin' AND intitule='path_to_upload_folder'"));
$path_to_upload_folder = $set[0];

// get previous saltkey
$set = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT valeur FROM ".$_SESSION['pre']."misc WHERE type='admin' AND intitule='saltkey_ante_2127'"));
$saltkey_ante_2127 = $set[0];

// get if files are managed through defuse library
$set = mysqli_fetch_row(mysqli_query($dbTmp, "SELECT valeur FROM ".$_SESSION['pre']."misc WHERE type='admin' AND intitule='files_with_defuse'"));
$files_with_defuse = $set[0];

// If $saltkey_ante_2127 is set to'none', then encryption is done with Defuse, so exit
// Also quit if no new defuse saltkey was generated
if ($files_with_defuse === "done") {
    echo '[{"finish":"1" , "next":"", "error":""}]';
    exit();
}

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

// load PhpEncryption library
$path = '../includes/libraries/Encryption/Encryption/';

require_once $path.'Crypto.php';
require_once $path.'Encoding.php';
require_once $path.'DerivedKeys.php';
require_once $path.'Key.php';
require_once $path.'KeyOrPassword.php';
require_once $path.'File.php';
require_once $path.'RuntimeTests.php';
require_once $path.'KeyProtectedByPassword.php';
require_once $path.'Core.php';


if (file_exists(SECUREPATH."/teampass-seckey.txt")) {
    // convert KEY
    $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");
    $defuse_key = \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key);

    // Prepare decryption options for Defuse
    $iv = substr(hash("md5", "iv".$ascii_key), 0, 8);
    $key = substr(hash("md5", "ssapmeat1".$ascii_key, true), 0, 24);
    $opts_decrypt_defuse = array('iv'=>$iv, 'key'=>$key);

    while ($data = mysqli_fetch_array($rows)) {
        if (file_exists($path_to_upload_folder.'/'.$data['file']) && $data['status'] === 'encrypted') {
            // Check if file is already encrypted with Defuse.
            // If yes, then stop
            try {
                \Defuse\Crypto\File::decryptFile(
                    $path_to_upload_folder.'/'.$data['file'],
                    $path_to_upload_folder.'/'.$data['file'].".defuse_test",
                    $defuse_key
                );
            } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                // Make a copy of file
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
                stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts_decrypt_defuse);
                // copy to file
                stream_copy_to_stream($fp, $out);
                // clean
                fclose($fp);
                fclose($out);
                unlink($path_to_upload_folder.'/'.$data['file'].".copy");

                // Now encrypt the file with new saltkey
                $err = '';
                try {
                    \Defuse\Crypto\File::encryptFile(
                        $path_to_upload_folder.'/'.$data['file'].".tmp",
                        $path_to_upload_folder.'/'.$data['file'],
                        $defuse_key
                    );
                } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                    $err = "encryption_not_possible";
                } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                    $err = $ex;
                } catch (Defuse\Crypto\Exception\IOException $ex) {
                    $err = $ex;
                }
                if (empty($err) === false) {
                    echo $err;
                }

                // clean
                unlink($path_to_upload_folder.'/'.$data['file'].".tmp");
            }
            // Clean if needed
            if (file_exists($path_to_upload_folder.'/'.$data['file'].".defuse_test")) {
                unlink($path_to_upload_folder.'/'.$data['file'].".defuse_test");
            }
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