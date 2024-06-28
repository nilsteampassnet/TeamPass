<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      upload.attachments.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;


// Load functions
require_once 'main.functions.php';
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
// init
loadClasses('DB');
$lang = new Language();

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('items') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

//check for session
if (null !== $request->request->filter('PHPSESSID', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
    session_id($request->request->filter('PHPSESSID', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
} elseif (null !== $request->query->get('PHPSESSID')) {
    session_id(filter_var($request->query->get('PHPSESSID'), FILTER_SANITIZE_FULL_SPECIAL_CHARS));
} else {
    handleAttachmentError('No Session was found.', 100);
}

// Prepare POST variables
$post_user_token = $request->request->filter('user_upload_token', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_type_upload = $request->request->filter('type_upload', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_itemId = $request->request->filter('itemId', null, FILTER_SANITIZE_NUMBER_INT);
$post_files_number = $request->request->filter('files_number', null, FILTER_SANITIZE_NUMBER_INT);
$post_timezone = $request->request->filter('timezone', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_isNewItem = $request->request->filter('isNewItem', null, FILTER_SANITIZE_NUMBER_INT);
$post_randomId = $request->request->filter('randomId', null, FILTER_SANITIZE_NUMBER_INT);
$post_isPersonal = $request->request->filter('isPersonal', null, FILTER_SANITIZE_NUMBER_INT);
$post_fileSize= $request->request->filter('file_size', null, FILTER_SANITIZE_NUMBER_INT);
$chunk = $request->request->filter('chunk', 0, FILTER_SANITIZE_NUMBER_INT);
$chunks = $request->request->filter('chunks', 0, FILTER_SANITIZE_NUMBER_INT);
$fileName = $request->request->filter('name', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// token check
if (null === $post_user_token) {
    handleAttachmentError('No user token found.', 110);
    exit();
} else {
    // delete expired tokens
    DB::delete(prefixTable('tokens'), 'end_timestamp < %i', time());

    if (
        null !== $session->get($post_user_token)
        && ($chunk < $chunks - 1)
        && $session->get($post_user_token) >= 0
    ) {
        // increase end_timestamp for token
        DB::update(
            prefixTable('tokens'),
            array(
                'end_timestamp' => time() + 10,
            ),
            'user_id = %i AND token = %s',
            $session->get('user-id'),
            $post_user_token
        );
    } else {
        // create a session if several files to upload
        if (
            null === $session->get($post_user_token)
            || empty($session->get($post_user_token)) === true
            || (int) $session->get($post_user_token) === 0
        ) {
            $session->set($post_user_token, $post_files_number);
        } elseif ((int) $session->get($post_user_token) > 0) {
            // increase end_timestamp for token
            DB::update(
                prefixTable('tokens'),
                array(
                    'end_timestamp' => time() + 30,
                ),
                'user_id = %i AND token = %s',
                $session->get('user-id'),
                $post_user_token
            );
            // decrease counter of files to upload
            $session->set($post_user_token, $session->get($post_user_token) - 1);
        } else {
            // no more files to upload, kill session
            $session->remove($post_user_token);
            handleAttachmentError('No user token found.', 110);
            die();
        }

        // check if token is expired
        $data = DB::queryFirstRow(
            'SELECT end_timestamp
            FROM ' . prefixTable('tokens') . '
            WHERE user_id = %i AND token = %s',
            $session->get('user-id'),
            $post_user_token
        );
        // clear user token
        if ((int) $session->get($post_user_token) === 0) {
            DB::delete(
                prefixTable('tokens'),
                'user_id = %i AND token = %s',
                $session->get('user-id'),
                $post_user_token
            );
            $session->remove($post_user_token);
        }

        if (time() > $data['end_timestamp']) {
            // too old
            $session->remove($post_user_token);
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
    handleAttachmentError('POST exceeded maximum allowed size.', 111, 413);
}

// Validate the file size (Warning: the largest files supported by this code is 2GB)
$file_size = @filesize($_FILES['file']['tmp_name']);
if ($file_size === false || (int) $file_size > (int) $max_file_size_in_bytes) {
    handleAttachmentError('File exceeds the maximum allowed size', 120, 413);
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
$file_name = preg_replace('[^A-Za-z0-9]', '', strtolower(basename($_FILES['file']['name'])));
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
    handleAttachmentError('Invalid file extension.', 115, 415);
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
            $fileFullSize += (int) $_FILES['file']['size'];

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
            'size' => $post_fileSize,
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
                'user_id' => (int) $session->get('user-id'),
                'share_key' => encryptUserObjectKey($newFile['objectKey'], $session->get('user-public_key')),
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
                'id_user' => $session->get('user-id'),
                'action' => 'at_modification',
                'raison' => 'at_add_file : ' . $fileName . ':' . $newID,
            )
        );
    }
}

// Return JSON-RPC response
die('{"jsonrpc" : "2.0", "result" : null, "id" : "' . $newID . '"}');

/**
 * Handle errors and kill script.
 *
 * @param string $message Message
 * 
 * @param integer $code    Code
 * 
 */
function handleAttachmentError($message, $code, $http_code = 400)
{
    // HTTP 40x code to avoid "success" in UI.
    http_response_code($http_code);

    // json error message
    echo '{"jsonrpc" : "2.0", "error" : {"code": ' . htmlentities((string) $code, ENT_QUOTES) . ', "message": "' . htmlentities((string) $message, ENT_QUOTES) . '"}, "id" : "id"}';
    
    // Force exit to avoid bypass filters.
    exit;
}
