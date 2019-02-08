<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2019 Nils Laumaillé
* @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
*
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
 */
require_once 'SecureHandler.php';
session_start();
if (isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
}

// Include files
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();

// Prepare GET variables
$get_filename = $superGlobal->get("name", "GET");
$get_fileid = $superGlobal->get("fileid", "GET");

// prepare Encryption class calls
use \Defuse\Crypto\Crypto;
use \Defuse\Crypto\File;
use \Defuse\Crypto\Exception as Ex;

header('Content-disposition: attachment; filename='.rawurldecode(basename($get_filename)));
header('Content-Type: application/octet-stream');
header('Cache-Control: must-revalidate, no-cache, no-store');
header('Expires: 0');
if (isset($_GET['pathIsFiles']) && $_GET['pathIsFiles'] == 1) {
    readfile($SETTINGS['path_to_files_folder'].'/'.basename($get_filename));
} else {
    include_once 'main.functions.php';

    // connect to the server
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    // get file key
    $file_info = DB::queryfirstrow(
        "SELECT file, status 
        FROM ".prefixTable("files")."
        WHERE id=%i",
        $get_fileid
    );

    // should we encrypt/decrypt the file
    encryptOrDecryptFile(
        $file_info['file'],
        $file_info['status'],
        $SETTINGS
    );

    // should we decrypt the attachment?
    if (isset($file_info['status']) && $file_info['status'] === "encrypted") {
        // load PhpEncryption library
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'Crypto.php';
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'Encoding.php';
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'DerivedKeys.php';
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'Key.php';
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'KeyOrPassword.php';
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'File.php';
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'RuntimeTests.php';
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'KeyProtectedByPassword.php';
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/'.'Core.php';

        // get KEY
        $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");

        // Now encrypt the file with new saltkey
        $err = '';
        try {
            \Defuse\Crypto\File::decryptFile(
                $SETTINGS['path_to_upload_folder'].'/'.$file_info['file'],
                $SETTINGS['path_to_upload_folder'].'/'.$file_info['file'].".delete",
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

        $fp = fopen($SETTINGS['path_to_upload_folder'].'/'.$file_info['file'].".delete", 'rb');
        if ($fp !== false) {
            // Read the file contents
            fpassthru($fp);

            // Close the file
            fclose($fp);
        }

        unlink($SETTINGS['path_to_upload_folder'].'/'.$file_info['file'].".delete");
    } else {
        $fp = fopen($SETTINGS['path_to_upload_folder'].'/'.$file_info['file'], 'rb');
        if ($fp !== false) {
            // Read the file contents
            fpassthru($fp);

            // Close the file
            fclose($fp);
        }
    }
}
