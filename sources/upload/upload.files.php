<?php
/**
 * @file 		upload.files.php
 * @author		Nils Laumaillé
 * @version 	2.1.16
 * @copyright 	(c) 2009-2012 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
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
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "items")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    handleUploadError('Not allowed to ...');
    exit();
}

//check for session
if (isset($_POST['PHPSESSID'])) {
    session_id($_POST['PHPSESSID']);
} elseif (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
} else {
    handleUploadError('No Session was found.');
}


// Get parameters
$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
$fileName = isset($_REQUEST["name"]) ? filter_var($_REQUEST["name"], FILTER_SANITIZE_STRING) : '';

// token check
if (!isset($_POST['user_token'])) {
    handleUploadError('No user token found.');
    exit();
} else {
    // delete expired tokens
    DB::delete(prefix_table("tokens"), "end_timestamp < %i", time());

    if ($chunk < ($chunks - 1)) {
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
        // check if token is expired
        $data = DB::queryFirstRow(
            "SELECT end_timestamp FROM ".prefix_table("tokens")." WHERE user_id = %i AND token = %s",
            $_SESSION['user_id'],
            $_POST['user_token']
        );
        // clear user token
        DB::delete(prefix_table("tokens"), "user_id = %i AND token = %s", $_SESSION['user_id'], $_POST['user_token']);

        if (time() > $data['end_timestamp']) {
            // too old
            handleAttachmentError('User token expired.', 110);
            die();
        }
    }
}


// HTTP headers for no cache etc
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// load functions
require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';

if (isset($_POST["type_upload"]) && $_POST["type_upload"] == "upload_profile_photo") {
    $targetDir = $_SESSION['settings']['cpassman_dir'].'/includes/avatars';
} else {
    $targetDir = $_SESSION['settings']['path_to_files_folder'];
}

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds
$valid_chars_regex = 'A-Za-z0-9_'; //accept only those characters
$MAX_FILENAME_LENGTH = 260;
$max_file_size_in_bytes = 2147483647; //2Go

if (isset($_POST['timezone'])) {
    date_default_timezone_set($_POST['timezone']);
}

// Check post_max_size
$POST_MAX_SIZE = ini_get('post_max_size');
$unit = strtoupper(substr($POST_MAX_SIZE, -1));
$multiplier = ($unit == 'M' ? 1048576 : ($unit == 'K' ? 1024 : ($unit == 'G' ? 1073741824 : 1)));
if ((int) $_SERVER['CONTENT_LENGTH'] > $multiplier * (int) $POST_MAX_SIZE && $POST_MAX_SIZE) {
    handleUploadError('POST exceeded maximum allowed size.');
}

// Validate the file size (Warning: the largest files supported by this code is 2GB)
$file_size = @filesize($_FILES['file']['tmp_name']);
if (!$file_size || $file_size > $max_file_size_in_bytes) {
    handleUploadError('File exceeds the maximum allowed size');
}
if ($file_size <= 0) {
    handleUploadError('File size outside allowed lower bound');
}

// 5 minutes execution time
set_time_limit(5 * 60);


// Validate the upload
if (!isset($_FILES['file'])) {
    handleUploadError('No upload found in $_FILES for Filedata');
} elseif (isset($_FILES['file']['error']) && $_FILES['file']['error'] != 0) {
    handleUploadError($uploadErrors[$_FILES['Filedata']['error']]);
} elseif (!isset($_FILES['file']['tmp_name']) || !@is_uploaded_file($_FILES['file']['tmp_name'])) {
    handleUploadError('Upload failed is_uploaded_file test.');
} elseif (!isset($_FILES['file']['name'])) {
    handleUploadError('File has no name.');
}


// Validate file name (for our purposes we'll just remove invalid characters)
$file_name = preg_replace(
    '/[^'.$valid_chars_regex.'\.]/',
    '',
    filter_var(
        strtolower(basename($_FILES['file']['name'])),
        FILTER_SANITIZE_STRING
    )
);
if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH) {
    handleUploadError('Invalid file name: '.$file_name.'.');
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
    handleUploadError('Invalid file extension.');
}

// is destination folder writable
if (is_writable($_SESSION['settings']['path_to_files_folder']) === false) {
    handleUploadError('Not enough permissions on folder '.$_SESSION['settings']['path_to_files_folder'].'.');
}

// Clean the fileName for security reasons
$fileName = preg_replace('/[^\w\.]+/', '_', $fileName);
$fileName = preg_replace('/[^'.$valid_chars_regex.'\.]/', '', strtolower(basename($fileName)));

// Make sure the fileName is unique but only if chunking is disabled
if ($chunks < 2 && file_exists($targetDir.DIRECTORY_SEPARATOR.$fileName)) {
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
            die(
                '{"jsonrpc" : "2.0",
                "error" : {"code": 102, "message": "Failed to open output stream."},
                "id" : "id"}'
            );
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

if (isset($_POST["type_upload"]) && $_POST["type_upload"] == "import_items_from_csv") {
    rename($filePath, $targetDir.DIRECTORY_SEPARATOR.filter_var($_POST["csvFile"], FILTER_SANITIZE_STRING));
} else if (isset($_POST["type_upload"]) && $_POST["type_upload"] == "import_items_from_keypass") {
    rename($filePath, $targetDir.DIRECTORY_SEPARATOR.filter_var($_POST["xmlFile"], FILTER_SANITIZE_STRING));
} else if (isset($_POST["type_upload"]) && $_POST["type_upload"] == "upload_profile_photo") {
    // sanitize the new file name
    $newFileName = preg_replace('/[^\w\._]+/', '_', $_POST['newFileName']);
    $newFileName = preg_replace('/[^'.$valid_chars_regex.'\.]/', '', strtolower(basename($newFileName)));

    // get file extension
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);

    // rename the file
    rename($filePath, $targetDir.DIRECTORY_SEPARATOR.$newFileName.'.'.$ext);

    // make thumbnail
    make_thumb(
        $targetDir.DIRECTORY_SEPARATOR.$newFileName.'.'.$ext,
        $targetDir.DIRECTORY_SEPARATOR.$newFileName."_thumb".'.'.$ext,
        40
    );

    //Connect to mysql server
    require_once '../../includes/config/settings.php';
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

    // get current avatar and delete it
    $data = DB::queryFirstRow("SELECT avatar, avatar_thumb FROM ".$pre."users WHERE id=%i", $_SESSION['user_id']);
    fileDelete($targetDir.DIRECTORY_SEPARATOR.$data['avatar']);
    fileDelete($targetDir.DIRECTORY_SEPARATOR.$data['avatar_thumb']);

    // store in DB the new avatar
    DB::query(
        "UPDATE ".$pre."users
        SET avatar='".$newFileName.'.'.$ext."', avatar_thumb='".$newFileName."_thumb".'.'.$ext."'
        WHERE id=%i",
        $_SESSION['user_id']
    );

    // store in session
    $_SESSION['user_avatar'] = $newFileName.'.'.$ext;
    $_SESSION['user_avatar_thumb'] = $newFileName."_thumb".'.'.$ext;

    echo '{"filename" : "'.$_SESSION['user_avatar'].'" , "filename_thumb" : "'.$_SESSION['user_avatar_thumb'].'"}';
    exit();

} else {
    rename($filePath, $targetDir.DIRECTORY_SEPARATOR.filter_var($_POST["File"], FILTER_SANITIZE_STRING));
}

// Return JSON-RPC response
die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');


/* Handles the error output. */
function handleUploadError($message)
{
    echo $message;
    exit(0);
}
