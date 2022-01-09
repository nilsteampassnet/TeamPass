<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      upload.attachments.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


require_once './SecureHandler.php';
session_name('teampass_session');
session_start();
if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS) !== true) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

//check for session
if (null !== filter_input(INPUT_POST, 'PHPSESSID', FILTER_SANITIZE_STRING)) {
    //session_id(filter_input(INPUT_POST, 'PHPSESSID', FILTER_SANITIZE_STRING));
    session_regenerate_id(true);
} elseif (isset($_GET['PHPSESSID'])) {
    //session_id(filter_var($_GET['PHPSESSID'], FILTER_SANITIZE_STRING));
    session_regenerate_id(true);
} else {
    handleAttachmentError('No Session was found.', '');
}

// Prepare POST variables
$post_user_token = filter_input(INPUT_POST, 'user_token', FILTER_SANITIZE_STRING);
$post_type_upload = filter_input(INPUT_POST, 'type_upload', FILTER_SANITIZE_STRING);
$post_itemId = filter_input(INPUT_POST, 'itemId', FILTER_SANITIZE_NUMBER_INT);
$post_files_number = filter_input(INPUT_POST, 'files_number', FILTER_SANITIZE_NUMBER_INT);
$post_timezone = filter_input(INPUT_POST, 'timezone', FILTER_SANITIZE_STRING);
$post_isNewItem = filter_input(INPUT_POST, 'isNewItem', FILTER_SANITIZE_NUMBER_INT);
$post_randomId = filter_input(INPUT_POST, 'randomId', FILTER_SANITIZE_NUMBER_INT);
$post_isPersonal = filter_input(INPUT_POST, 'isPersonal', FILTER_SANITIZE_NUMBER_INT);

// load functions
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

// Get parameters
$chunk = isset($_REQUEST['chunk']) ? (int) $_REQUEST['chunk'] : 0;
$chunks = isset($_REQUEST['chunks']) ? (int) $_REQUEST['chunks'] : 0;
$fileName = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';

// token check
if (null === $post_user_token) {
    handleAttachmentError('No user token found.', 110);
    exit();
} else {
    //Connect to mysql server
    include_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
    include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
    if (defined('DB_PASSWD_CLEAR') === false) {
        define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
    }
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = DB_PASSWD_CLEAR;
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    // delete expired tokens
    DB::delete(prefixTable('tokens'), 'end_timestamp < %i', time());

    if (
        isset($_SESSION[$post_user_token])
        && ($chunk < $chunks - 1)
        && $_SESSION[$post_user_token] >= 0
    ) {
        // increase end_timestamp for token
        DB::update(
            prefixTable('tokens'),
            array(
                'end_timestamp' => time() + 10,
            ),
            'user_id = %i AND token = %s',
            $_SESSION['user_id'],
            $post_user_token
        );
    } else {
        // create a session if several files to upload
        if (
            isset($_SESSION[$post_user_token]) === false
            || empty($_SESSION[$post_user_token])
            || $_SESSION[$post_user_token] === '0'
        ) {
            $_SESSION[$post_user_token] = $post_files_number;
        } elseif ($_SESSION[$post_user_token] > 0) {
            // increase end_timestamp for token
            DB::update(
                prefixTable('tokens'),
                array(
                    'end_timestamp' => time() + 30,
                ),
                'user_id = %i AND token = %s',
                $_SESSION['user_id'],
                $post_user_token
            );
            // decrease counter of files to upload
            --$_SESSION[$post_user_token];
        } else {
            // no more files to upload, kill session
            unset($_SESSION[$post_user_token]);
            handleAttachmentError('No user token found.', 110);
            die();
        }

        // check if token is expired
        $data = DB::queryFirstRow(
            'SELECT end_timestamp
            FROM ' . prefixTable('tokens') . '
            WHERE user_id = %i AND token = %s',
            $_SESSION['user_id'],
            $post_user_token
        );
        // clear user token
        if ($_SESSION[$post_user_token] === 0) {
            DB::delete(
                prefixTable('tokens'),
                'user_id = %i AND token = %s',
                $_SESSION['user_id'],
                $post_user_token
            );
            unset($_SESSION[$post_user_token]);
        }

        if (time() > $data['end_timestamp']) {
            // too old
            unset($_SESSION[$post_user_token]);
            handleAttachmentError('User token expired.', 110);
            die();
        }
    }

    // Load Settings
    include_once $SETTINGS['cpassman_dir'] . '/includes/config/tp.config.php';
}

// HTTP headers for no cache etc
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);

$targetDir = $SETTINGS['path_to_upload_folder'];

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds
$valid_chars_regex = 'A-Za-z0-9'; //accept only those characters
$MAX_FILENAME_LENGTH = 260;
$max_file_size_in_bytes = 2147483647; //2Go

if (null !== $post_timezone) {
    date_default_timezone_set($post_timezone);
}

// Check post_max_size
$POST_MAX_SIZE = ini_get('post_max_size');
$unit = strtoupper(substr($POST_MAX_SIZE, -1));
$multiplier = ($unit == 'M' ? 1048576 : ($unit == 'K' ? 1024 : ($unit == 'G' ? 1073741824 : 1)));
if ((int) $_SERVER['CONTENT_LENGTH'] > $multiplier * (int) $POST_MAX_SIZE && $POST_MAX_SIZE) {
    handleAttachmentError('POST exceeded maximum allowed size.', 111);
}

// Validate the file size (Warning: the largest files supported by this code is 2GB)
$file_size = @filesize($_FILES['file']['tmp_name']);
if ($file_size === false || (int) $file_size > (int) $max_file_size_in_bytes) {
    handleAttachmentError('File exceeds the maximum allowed size', 120);
}
if ($file_size <= 0) {
    handleAttachmentError('File size outside allowed lower bound', 112);
}

// Validate the upload
if (!isset($_FILES['file'])) {
    handleAttachmentError('No upload found in $_FILES for Filedata', 121);
} elseif (isset($_FILES['file']['error']) && $_FILES['file']['error'] != 0) {
    handleAttachmentError($uploadErrors[$_FILES['Filedata']['error']], 122);
} elseif (!isset($_FILES['file']['tmp_name']) || !@is_uploaded_file($_FILES['file']['tmp_name'])) {
    handleAttachmentError('Upload failed is_uploaded_file test.', 123);
} elseif (!isset($_FILES['file']['name'])) {
    handleAttachmentError('File has no name.', 113);
}

// Validate file name (for our purposes we'll just remove invalid characters)
$file_name = preg_replace('[^' . $valid_chars_regex . ']', '', strtolower(basename($_FILES['file']['name'])));
if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH) {
    handleAttachmentError('Invalid file name: ' . $file_name . '.', 114);
}

// Validate file extension
$ext = strtolower(getFileExtension($_REQUEST['name']));
if (
    in_array(
        $ext,
        explode(
            ',',
            $SETTINGS['upload_docext'] . ',' . $SETTINGS['upload_imagesext'] .
                ',' . $SETTINGS['upload_pkgext'] . ',' . $SETTINGS['upload_otherext']
        )
    ) === false
) {
    handleAttachmentError('Invalid file extension.', 115);
}

// 5 minutes execution time
set_time_limit(5 * 60);

// Clean the fileName for security reasons
$fileInfo = pathinfo($fileName);
$fileName = base64_encode($fileInfo['filename']) . '.' . $fileInfo['extension'];
$fileFullSize = 0;

// Make sure the fileName is unique but only if chunking is disabled
if ($chunks < 2 && file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
    $ext = strrpos($fileName, '.');
    $fileNameA = substr($fileName, 0, $ext);
    $fileNameB = substr($fileName, $ext);

    $count = 1;
    while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileNameA . '_' . $count . $fileNameB)) {
        ++$count;
    }

    $fileName = $fileNameA . '_' . $count . $fileNameB;
}

$filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

// Create target dir
if (file_exists($targetDir) === false) {
    try {
        mkdir($targetDir, 0777, true);
    } catch (Exception $e) {
        print_r($e);
    }
}

// Remove old temp files
if ($cleanupTargetDir && is_dir($targetDir) && ($dir = opendir($targetDir))) {
    while (($file = readdir($dir)) !== false) {
        $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

        // Remove temp file if it is older than the max age and is not the current file
        if (
            preg_match('/\.part$/', $file)
            && (filemtime($tmpfilePath) < time() - $maxFileAge)
            && ($tmpfilePath != "{$filePath}.part")
        ) {
            fileDelete($tmpfilePath, $SETTINGS);
        }
    }

    closedir($dir);
} else {
    die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
}

// Look for the content type header
if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
    $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
}

if (isset($_SERVER['CONTENT_TYPE'])) {
    $contentType = $_SERVER['CONTENT_TYPE'];
}

// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
if (strpos($contentType, 'multipart') !== false) {
    if (isset($_FILES['file']['tmp_name']) === true && is_uploaded_file($_FILES['file']['tmp_name']) === true) {
        // Open temp file
        $out = fopen("{$filePath}.part", $chunk == 0 ? 'wb' : 'ab');

        if ($out !== false) {
            // Read binary input stream and append it to temp file
            $in = fopen($_FILES['file']['tmp_name'], 'rb');
            $fileFullSize += $_FILES['file']['size'];

            if ($in !== false) {
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
            } else {
                die('{"jsonrpc" : "2.0",
                    "error" : {"code": 101, "message": "Failed to open input stream."},
                    "id" : "id"}');
            }
            fclose($in);
            fclose($out);

            fileDelete($_FILES['file']['tmp_name'], $SETTINGS);
        } else {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }
    } else {
        die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
    }
} else {
    // Open temp file
    $out = fopen("{$filePath}.part", $chunk == 0 ? 'wb' : 'ab');

    if ($out !== false) {
        // Read binary input stream and append it to temp file
        $in = fopen('php://input', 'rb');

        if ($in !== false) {
            while ($buff = fread($in, 4096)) {
                fwrite($out, $buff);
            }
        } else {
            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
        }
        fclose($in);
        fclose($out);
    } else {
        die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
    }
}

// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1) {
    // Strip the temp .part suffix off
    rename("{$filePath}.part", $filePath);
} else {
    // continue uploading other chunks
    die();
}

// Encrypt the file if requested
$newFile = encryptFile($fileName, $targetDir);

// Case ITEM ATTACHMENTS - Store to database
if (null !== $post_type_upload && $post_type_upload === 'item_attachments') {
    // Check case of new item
    if (
        isset($post_isNewItem) === true
        && (int) $post_isNewItem === 1
        && empty($post_randomId) === false
    ) {
        $post_itemId = $post_randomId;
    }

    DB::insert(
        prefixTable('files'),
        array(
            'id_item' => $post_itemId,
            'name' => $fileName,
            'size' => $fileFullSize,
            'extension' => $fileInfo['extension'],
            'type' => $_FILES['file']['type'],
            'file' => $newFile['fileHash'],
            'status' => TP_ENCRYPTION_NAME,
        )
    );
    $newID = DB::insertId();

    // Store the key for users
    // Is this item a personal one?
    if ((int) $post_isPersonal === 0) {
        // It is not a personal item objectKey
        // This is a public object
        $users = DB::query(
            'SELECT id, public_key
            FROM ' . prefixTable('users') . '
            WHERE id NOT IN ("' . OTV_USER_ID . '","' . SSH_USER_ID . '","' . API_USER_ID . '")
            AND public_key != ""'
        );
        foreach ($users as $user) {
            // Insert in DB the new object key for this item by user
            DB::insert(
                prefixTable('sharekeys_files'),
                array(
                    'object_id' => $newID,
                    'user_id' => (int) $user['id'],
                    'share_key' => encryptUserObjectKey($newFile['objectKey'], $user['public_key']),
                )
            );
        }
    } else {
        DB::insert(
            prefixTable('sharekeys_files'),
            array(
                'object_id' => (int) $newID,
                'user_id' => (int) $_SESSION['user_id'],
                'share_key' => encryptUserObjectKey($newFile['objectKey'], $_SESSION['user']['public_key']),
            )
        );
    }

    // Log upload into databse
    if (
        isset($post_isNewItem) === false
        || (isset($post_isNewItem) === true
            && ($post_isNewItem === false || (int) $post_isNewItem !== 1))
    ) {
        DB::insert(
            prefixTable('log_items'),
            array(
                'id_item' => $post_itemId,
                'date' => time(),
                'id_user' => $_SESSION['user_id'],
                'action' => 'at_modification',
                'raison' => 'at_add_file : ' . $fileName . ':' . $newID,
            )
        );
    }
}

// Return JSON-RPC response
die('{"jsonrpc" : "2.0", "result" : null, "id" : "' . $newID . '"}');

/**
 * Undocumented function.
 *
 * @param string $message Message
 * @param string $code    Code
 */
function handleAttachmentError($message, $code)
{
    echo '{"jsonrpc" : "2.0", "error" : {"code": ' . htmlentities($code, ENT_QUOTES) . ', "message": "' . htmlentities($message, ENT_QUOTES) . '"}, "id" : "id"}';
}
