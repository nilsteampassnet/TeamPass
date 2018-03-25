<?php
/**
 * @file          upload.attachments.php
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

require_once('../SecureHandler.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../../includes/config/tp.config.php')) {
    require_once '../../includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once '../checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "items")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    handleAttachmentError('Not allowed to ...', 110);
    exit();
}

//check for session
if (null !== filter_input(INPUT_POST, 'PHPSESSID', FILTER_SANITIZE_STRING)) {
    session_id(filter_input(INPUT_POST, 'PHPSESSID', FILTER_SANITIZE_STRING));
} elseif (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
} else {
    handleAttachmentError('No Session was found.', 110);
}

// load functions
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';


// Get parameters
$chunk = isset($_REQUEST["chunk"]) ? (int) $_REQUEST["chunk"] : 0;
$chunks = isset($_REQUEST["chunks"]) ? (int) $_REQUEST["chunks"] : 0;
$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';


// token check
if (null === filter_input(INPUT_POST, 'user_token', FILTER_SANITIZE_STRING)) {
    handleAttachmentError('No user token found.', 110);
    exit();
} else {
    //Connect to mysql server
    require_once '../../includes/config/settings.php';
    require_once '../../includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // delete expired tokens
    DB::delete(prefix_table("tokens"), "end_timestamp < %i", time());

    // Prepare POST variables
    $post_user_token = filter_input(INPUT_POST, 'user_token', FILTER_SANITIZE_STRING);
    $post_type_upload = filter_input(INPUT_POST, 'type_upload', FILTER_SANITIZE_STRING);
    $post_itemId = filter_input(INPUT_POST, 'itemId', FILTER_SANITIZE_NUMBER_INT);
    $post_files_number = filter_input(INPUT_POST, 'files_number', FILTER_SANITIZE_NUMBER_INT);
    $post_timezone = filter_input(INPUT_POST, 'timezone', FILTER_SANITIZE_STRING);

    if (isset($_SESSION[$post_user_token])
        && ($chunk < $chunks - 1)
        && $_SESSION[$post_user_token] >= 0
    ) {
        // increase end_timestamp for token
        DB::update(
            prefix_table('tokens'),
            array(
                'end_timestamp' => time() + 10
                ),
            "user_id = %i AND token = %s",
            $_SESSION['user_id'],
            $post_user_token
        );
    } else {
        // create a session if several files to upload
        if (isset($_SESSION[$post_user_token]) === false
            || empty($_SESSION[$post_user_token])
            || $_SESSION[$post_user_token] === "0"
        ) {
            $_SESSION[$post_user_token] = $post_files_number;
        } elseif ($_SESSION[$post_user_token] > 0) {
            // increase end_timestamp for token
            DB::update(
                prefix_table('tokens'),
                array(
                    'end_timestamp' => time() + 30
                    ),
                "user_id = %i AND token = %s",
                $_SESSION['user_id'],
                $post_user_token
            );
            // decrease counter of files to upload
            $_SESSION[$post_user_token]--;
        } else {
            // no more files to upload, kill session
            unset($_SESSION[$post_user_token]);
            handleAttachmentError('No user token found.', 110);
            die();
        }

        // check if token is expired
        $data = DB::queryFirstRow(
            "SELECT end_timestamp FROM ".prefix_table("tokens")." WHERE user_id = %i AND token = %s",
            $_SESSION['user_id'],
            $post_user_token
        );
        // clear user token
        if ($_SESSION[$post_user_token] === 0) {
            DB::delete(
                prefix_table("tokens"),
                "user_id = %i AND token = %s",
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
    require_once $SETTINGS['cpassman_dir'].'/includes/config/tp.config.php';
}

// HTTP headers for no cache etc
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);

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
if (!$file_size || $file_size > $max_file_size_in_bytes) {
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
$file_name = preg_replace('[^'.$valid_chars_regex.']', '', strtolower(basename($_FILES['file']['name'])));
if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH) {
    handleAttachmentError('Invalid file name: '.$file_name.'.', 114);
}

// Validate file extension
$ext = strtolower(getFileExtension($_REQUEST["name"]));
if (!in_array(
    $ext,
    explode(
        ',',
        $SETTINGS['upload_docext'].','.$SETTINGS['upload_imagesext'].
        ','.$SETTINGS['upload_pkgext'].','.$SETTINGS['upload_otherext']
    )
)) {
    handleAttachmentError('Invalid file extension.', 115);
}

// 5 minutes execution time
set_time_limit(5 * 60);

// Clean the fileName for security reasons
$fileName = preg_replace('/[^\w\._]+/', '_', $fileName);
$fileName = preg_replace('[^'.$valid_chars_regex.']', '', strtolower(basename($fileName)));

// Make sure the fileName is unique but only if chunking is disabled
if ($chunks < 2 && file_exists($targetDir.DIRECTORY_SEPARATOR.$fileName)) {
    $ext = strrpos($fileName, '.');
    $fileNameA = substr($fileName, 0, $ext);
    $fileNameB = substr($fileName, $ext);

    $count = 1;
    while (file_exists($targetDir.DIRECTORY_SEPARATOR.$fileNameA.'_'.$count.$fileNameB)) {
        $count++;
    }

    $fileName = $fileNameA.'_'.$count.$fileNameB;
}


$filePath = $targetDir.DIRECTORY_SEPARATOR.$fileName;

// Create target dir
if (!file_exists($targetDir)) {
    try {
        mkdir($targetDir, 0777, true);
    } catch (Exception $e) {
        print_r($e);
    }
}

// Remove old temp files
if ($cleanupTargetDir && is_dir($targetDir) && ($dir = opendir($targetDir))) {
    while (($file = readdir($dir)) !== false) {
        $tmpfilePath = $targetDir.DIRECTORY_SEPARATOR.$file;

        // Remove temp file if it is older than the max age and is not the current file
        if (preg_match('/\.part$/', $file)
            && (filemtime($tmpfilePath) < time() - $maxFileAge)
            && ($tmpfilePath != "{$filePath}.part")
        ) {
            fileDelete($tmpfilePath);
        }
    }

    closedir($dir);
} else {
    die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory."}, "id" : "id"}');
}

// Look for the content type header
if (isset($_SERVER["HTTP_CONTENT_TYPE"])) {
    $contentType = $_SERVER["HTTP_CONTENT_TYPE"];
}

if (isset($_SERVER["CONTENT_TYPE"])) {
    $contentType = $_SERVER["CONTENT_TYPE"];
}

// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
if (strpos($contentType, "multipart") !== false) {
    if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        // Open temp file
        $out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");

        if ($out) {
            // Read binary input stream and append it to temp file
            $in = fopen($_FILES['file']['tmp_name'], "rb");

            if ($in) {
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
            } else {
                die(
                    '{"jsonrpc" : "2.0",
                    "error" : {"code": 101, "message": "Failed to open input stream."},
                    "id" : "id"}'
                );
            }
            fclose($in);
            fclose($out);

            fileDelete($_FILES['file']['tmp_name']);
        } else {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }
    } else {
        die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
    }
} else {
    // Open temp file
    $out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");

    if ($out) {
        // Read binary input stream and append it to temp file
        $in = fopen("php://input", "rb");

        if ($in) {
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

// Get some variables
$fileRandomId = md5($fileName.time());
rename($filePath, $targetDir.DIRECTORY_SEPARATOR.$fileRandomId);


// Encrypt the file if requested
if (isset($SETTINGS['enable_attachment_encryption']) && $SETTINGS['enable_attachment_encryption'] === '1') {
    // Do encryption
    prepareFileWithDefuse(
        'encrypt',
        $targetDir.DIRECTORY_SEPARATOR.$fileRandomId,
        $targetDir.DIRECTORY_SEPARATOR.$fileRandomId."_encrypted"
    );

    // Do cleanup of files
    unlink($targetDir.DIRECTORY_SEPARATOR.$fileRandomId);
    rename(
        $targetDir.DIRECTORY_SEPARATOR.$fileRandomId."_encrypted",
        $targetDir.DIRECTORY_SEPARATOR.$fileRandomId
    );

    $file_status = "encrypted";
} else {
    $file_status = "clear";
}

// Case ITEM ATTACHMENTS - Store to database
if (null !== $post_type_upload && $post_type_upload === "item_attachments") {
    DB::insert(
        $pre.'files',
        array(
            'id_item' => $post_itemId,
            'name' => $fileName,
            'size' => $_FILES['file']['size'],
            'extension' => getFileExtension($fileName),
            'type' => $_FILES['file']['type'],
            'file' => $fileRandomId,
            'status' => $file_status
        )
    );

    // Log upload into databse
    DB::insert(
        $pre.'log_items',
        array(
            'id_item' => $post_itemId,
            'date' => time(),
            'id_user' => $_SESSION['user_id'],
            'action' => 'at_modification',
            'raison' => 'at_add_file : '.addslashes($fileName)
        )
    );
}

// Return JSON-RPC response
die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');


/* Handles the error output. */
function handleAttachmentError($message, $code)
{
    echo '{"jsonrpc" : "2.0", "error" : {"code": '.htmlentities($code, ENT_QUOTES).', "message": "'.htmlentities($message, ENT_QUOTES).'"}, "id" : "id"}';
}
