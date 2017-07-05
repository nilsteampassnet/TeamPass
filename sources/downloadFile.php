<?php
/**
 * @file          downloadFile.php
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

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || !isset($_SESSION['key_tmp']) || !isset($_SESSION['key']) || $_SESSION['CPM'] != 1 || $_GET['key'] != $_SESSION['key'] || $_GET['key_tmp'] != $_SESSION['key_tmp'] || empty($_SESSION['key']) || empty($_SESSION['key_tmp'])) {
    die('Hacking attempt...');
}

// prepare Encryption class calls
use \Defuse\Crypto\Crypto;
use \Defuse\Crypto\File;
use \Defuse\Crypto\Exception as Ex;

$get_filename = filter_var($_GET['name'], FILTER_SANITIZE_STRING);

header('Content-disposition: attachment; filename='.rawurldecode(basename($get_filename)));
header('Content-Type: application/octet-stream');
header('Pragma: no-cache');
header('Cache-Control: must-revalidate, no-cache, no-store');
header('Expires: 0');
if (isset($_GET['pathIsFiles']) && $_GET['pathIsFiles'] == 1) {
    readfile($_SESSION['settings']['path_to_files_folder'].'/'.basename($get_filename));
} else {
    require_once 'main.functions.php';
    // connect to DB
    include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // get file key
    $file_info = DB::queryfirstrow("SELECT file, status FROM ".prefix_table("files")." WHERE id=%i", $_GET['fileid']);

    // should we encrypt/decrypt the file
    encrypt_or_decrypt_file($file_info['file'], $file_info['status']);

    // should we decrypt the attachment?
    if (isset($file_info['status']) && $file_info['status'] === "encrypted") {
        // load PhpEncryption library
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'Crypto.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'Encoding.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'DerivedKeys.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'Key.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'KeyOrPassword.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'File.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'RuntimeTests.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'KeyProtectedByPassword.php';
        require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'Core.php';

        // get KEY
        $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");

        // Now encrypt the file with new saltkey
        $err = '';
        try {
            \Defuse\Crypto\File::decryptFile(
                $_SESSION['settings']['path_to_upload_folder'].'/'.$file_info['file'],
                $_SESSION['settings']['path_to_upload_folder'].'/'.$file_info['file'].".delete",
                \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key)
            );
        } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
            $err = "An attack! Either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.";
        } catch (Defuse\Crypto\Exception\BadFormatException $ex) {
            $err = $ex;
        } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
            $err = $ex;
        } catch (Defuse\Crypto\Exception\CryptoException $ex) {
            $err = $ex;
        } catch (Defuse\Crypto\Exception\IOException $ex) {
            $err = $ex;
        }
        if (empty($err) === false) {
            echo $err;
        }

        $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$file_info['file'].".delete", 'rb');

        // Read the file contents
        fpassthru($fp);

        // Close the file
        fclose($fp);

        unlink($_SESSION['settings']['path_to_upload_folder'].'/'.$file_info['file'].".delete");
    } else {
        $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$file_info['file'], 'rb');

        // Read the file contents
        fpassthru($fp);

        // Close the file
        fclose($fp);
    }
}