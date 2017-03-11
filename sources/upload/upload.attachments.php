<?php
/**
 * @file          upload.attachments.php
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

require_once('../SecureHandler.php');
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

/* do checks */
require_once '../checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "items")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    handleError('Not allowed to ...', 110);
    exit();
}

//check for session
if (isset($_POST['PHPSESSID'])) {
    session_id($_POST['PHPSESSID']);
} elseif (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
} else {
    handleError('No Session was found.', 110);
}


// Get parameters
$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';


// token check
if (!isset($_POST['user_token'])) {
    handleError('No user token found.', 110);
    exit();
} else {
    //Connect to mysql server
    require_once '../../includes/config/settings.php';
    require_once '../../includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = 'db_error_handler';
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // delete expired tokens
    DB::delete(prefix_table("tokens"), "end_timestamp < %i", time());

    if (isset($_SESSION[$_POST['user_token']]) && ($chunk < $chunks - 1) && $_SESSION[$_POST['user_token']] >= 0) {
        // increase end_timestamp for token
        DB::update(
            prefix_table('tokens'),
            array(
                'end_timestamp' => time() + 10
               ),
            "user_id = %i AND token = %s",
            $_SESSION['user_id'],
            $_POST['user_token']
        );
    } else {

        // create a session if several files to upload
        if (!isset($_SESSION[$_POST['user_token']]) || empty($_SESSION[$_POST['user_token']]) || $_SESSION[$_POST['user_token']] === 0) {
            $_SESSION[$_POST['user_token']] = $_POST['files_number'];
        } else if ($_SESSION[$_POST['user_token']] > 0) {
            // increase end_timestamp for token
            DB::update(
                prefix_table('tokens'),
                array(
                    'end_timestamp' => time() + 30
                   ),
                "user_id = %i AND token = %s",
                $_SESSION['user_id'],
                $_POST['user_token']
            );
            // decrease counter of files to upload
            $_SESSION[$_POST['user_token']]--;
        } else {
            // no more files to upload, kill session
            unset($_SESSION[$_POST['user_token']]);
            handleError('No user token found.', 110);
            die();
        }

        // check if token is expired
        $data = DB::queryFirstRow(
            "SELECT end_timestamp FROM ".prefix_table("tokens")." WHERE user_id = %i AND token = %s",
            $_SESSION['user_id'],
            $_POST['user_token']
        );
        // clear user token
        if ($_SESSION[$_POST['user_token']] === 0) {
            DB::delete(prefix_table("tokens"), "user_id = %i AND token = %s", $_SESSION['user_id'], $_POST['user_token']);
            unset($_SESSION[$_POST['user_token']]);
        }

        if (time() <= $data['end_timestamp']) {
            // it is ok
        } else {
            // too old
            unset($_SESSION[$_POST['user_token']]);
            handleError('User token expired.', 110);
            die();
        }
    }
}

// HTTP headers for no cache etc
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$targetDir = $_SESSION['settings']['path_to_upload_folder'];

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds
$valid_chars_regex = 'A-Za-z0-9';   //accept only those characters
$MAX_FILENAME_LENGTH = 260;
$max_file_size_in_bytes = 2147483647;   //2Go

@date_default_timezone_set($_POST['timezone']);

// Check post_max_size
$POST_MAX_SIZE = ini_get('post_max_size');
$unit = strtoupper(substr($POST_MAX_SIZE, -1));
$multiplier = ($unit == 'M' ? 1048576 : ($unit == 'K' ? 1024 : ($unit == 'G' ? 1073741824 : 1)));
if ((int) $_SERVER['CONTENT_LENGTH'] > $multiplier*(int) $POST_MAX_SIZE && $POST_MAX_SIZE) {
    handleError('POST exceeded maximum allowed size.', 111);
}

// Validate the file size (Warning: the largest files supported by this code is 2GB)
$file_size = @filesize($_FILES['file']['tmp_name']);
if (!$file_size || $file_size > $max_file_size_in_bytes) {
    handleError('File exceeds the maximum allowed size', 120);
}
if ($file_size <= 0) {
    handleError('File size outside allowed lower bound', 112);
}

// Validate the upload
if (!isset($_FILES['file'])) {
    handleError('No upload found in $_FILES for Filedata', 121);
} elseif (isset($_FILES['file']['error']) && $_FILES['file']['error'] != 0) {
    handleError($uploadErrors[$_FILES['Filedata']['error']], 122);
} elseif (!isset($_FILES['file']['tmp_name']) || !@is_uploaded_file($_FILES['file']['tmp_name'])) {
    handleError('Upload failed is_uploaded_file test.', 123);
} elseif (!isset($_FILES['file']['name'])) {
    handleError('File has no name.', 113);
}

// Validate file name (for our purposes we'll just remove invalid characters)
$file_name = preg_replace('[^'.$valid_chars_regex.']', '', strtolower(basename($_FILES['file']['name'])));
if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH) {
    handleError('Invalid file name: '.$file_name.'.', 114);
}

// Validate file extension
$ext = strtolower(getFileExtension($_REQUEST["name"]));
if (!in_array(
    $ext,
    explode(
        ',',
        $_SESSION['settings']['upload_docext'].','.$_SESSION['settings']['upload_imagesext'].
        ','.$_SESSION['settings']['upload_pkgext'].','.$_SESSION['settings']['upload_otherext']
    )
)) {
    handleError('Invalid file extension.', 115);
}

// 5 minutes execution time
@set_time_limit(5 * 60);

// Uncomment this one to fake upload time
// usleep(5000);

// Clean the fileName for security reasons
$fileName = preg_replace('/[^\w\._]+/', '_', $fileName);
$fileName = preg_replace('[^'.$valid_chars_regex.']', '', strtolower(basename($fileName)));

// Make sure the fileName is unique but only if chunking is disabled
if ($chunks < 2 && file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
    $ext = strrpos($fileName, '.');
    $fileNameA = substr($fileName, 0, $ext);
    $fileNameB = substr($fileName, $ext);

    $count = 1;
    while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileNameA . '_' . $count . $fileNameB)) {
        $count++;
    }

    $fileName = $fileNameA . '_' . $count . $fileNameB;
}

$filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

// Create target dir
if (!file_exists($targetDir)) {
    @mkdir($targetDir);
}

// Remove old temp files
if ($cleanupTargetDir && is_dir($targetDir) && ($dir = opendir($targetDir))) {
    while (($file = readdir($dir)) !== false) {
        $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

        // Remove temp file if it is older than the max age and is not the current file
        if (preg_match('/\.part$/', $file)
            && (filemtime($tmpfilePath) < time() - $maxFileAge)
            && ($tmpfilePath != "{$filePath}.part")
        ) {
            @unlink($tmpfilePath);
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

// should we encrypt the attachment?
if (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1) {
    // get key
    if (empty($ascii_key)) {
        $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");
    }

    // prepare encryption of attachment
    $iv = substr(hash("md5", "iv".$ascii_key), 0, 8);
    $key = substr(hash("md5", "ssapmeat1".$ascii_key, true), 0, 24);
    $opts = array('iv'=>$iv, 'key'=>$key);

    $file_status = "encrypted";
} else {
    $file_status = "clear";
}

// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
if (strpos($contentType, "multipart") !== false) {
    if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        // Open temp file
        $out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");

        if (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] === "1") {
            // Add the Mcrypt stream filter
            stream_filter_append($out, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts);
        }

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
            @unlink($_FILES['file']['tmp_name']);
        } else {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }
    } else {
        die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
    }
} else {
    // Open temp file
    $out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");

    if (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] === "1") {
        // Add the Mcrypt stream filter
        stream_filter_append($out, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts);
    }

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
rename($filePath, $targetDir . DIRECTORY_SEPARATOR . $fileRandomId);

//Get data from DB
/*$data = DB::queryfirstrow(
    "SELECT valeur FROM ".$pre."misc
    WHERE type=%s AND intitule=%s",
    "admin",
    "path_to_upload_folder"
);*/

// Case ITEM ATTACHMENTS - Store to database
if (isset($_POST['edit_item']) && $_POST['type_upload'] == "item_attachments") {
    DB::insert(
        $pre.'files',
        array(
            'id_item' => $_POST['itemId'],
            'name' => $fileName,
            'size' => $_FILES['file']['size'],
            'extension' => getFileExtension($fileName),
            'type' => $_FILES['file']['type'],
            'file' => $fileRandomId,
            'status' => $file_status
        )
    );
    // Log upload into databse only if "item edition"
    if (isset($_POST['edit_item']) && $_POST['edit_item'] == true) {
        DB::insert(
            $pre.'log_items',
            array(
                'id_item' => $_POST['itemId'],
                'date' => time(),
                'id_user' => $_SESSION['user_id'],
                'action' => 'at_modification',
                'raison' => 'at_add_file : '.addslashes($fileName)
            )
        );
    }
}

// Return JSON-RPC response
die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');

// Permits to extract the file extension
function getFileExtension($f)
{
    if (strpos($f, '.') === false) {
        return $f;
    }

    return substr($f, strrpos($f, '.')+1);
}

/* Handles the error output. */
function handleError($message, $code)
{
    echo '{"jsonrpc" : "2.0", "error" : {"code": '.$code.', "message": "'.$message.'"}, "id" : "id"}';
    exit(0);
}