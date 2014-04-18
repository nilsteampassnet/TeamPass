<?php
/**
 * @file 		upload.attachments.php
 * @author		Nils Laumaillé
 * @version 	2.1.20
 * @copyright 	(c) 2009-2014 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('../sessions.php');
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

// HTTP headers for no cache etc
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$targetDir = $_SESSION['settings']['path_to_upload_folder'];

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds
$valid_chars_regex = 'A-Za-z0-9';	//accept only those characters
$MAX_FILENAME_LENGTH = 260;
$max_file_size_in_bytes = 2147483647;	//2Go

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
    handleError('File exceeds the maximum allowed size');
}
if ($file_size <= 0) {
    handleError('File size outside allowed lower bound', 112);
}

// Validate the upload
if (!isset($_FILES['file'])) {
    handleError('No upload found in $_FILES for Filedata');
} elseif (isset($_FILES['file']['error']) && $_FILES['file']['error'] != 0) {
    handleError($uploadErrors[$_FILES['Filedata']['error']]);
} elseif (!isset($_FILES['file']['tmp_name']) || !@is_uploaded_file($_FILES['file']['tmp_name'])) {
    handleError('Upload failed is_uploaded_file test.');
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

// Get parameters
$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
$fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

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
    // prepare encryption of attachment
    include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
    $iv = substr(md5("\x1B\x3C\x58".SALT, true), 0, 8);
    $key = substr(
        md5("\x2D\xFC\xD8".SALT, true).
        md5("\x2D\xFC\xD9".SALT, true),
        0,
        24
    );
    $opts = array('iv'=>$iv, 'key'=>$key);
}

// Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
if (strpos($contentType, "multipart") !== false) {
    if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        // Open temp file
        $out = fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");

        if (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1) {
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

    if (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1) {
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
}

//if (isset($_POST['type_upload']) && $_POST['type_upload'] == "item_attachments") {    //&& $_POST['type_upload'] != "restore_db")

    // Get some variables
    $fileRandomId = md5($fileName.time());
    rename($filePath, $targetDir . DIRECTORY_SEPARATOR . $fileRandomId);

    require_once '../SplClassLoader.php';
    //Connect to mysql server
    require_once '../../includes/settings.php';
    $db = new SplClassLoader('Database\Core', '../../includes/libraries');
    $db->register();
    $db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
    $db->connect();

    //Get data from DB
    $sql = "SELECT valeur FROM ".$pre."misc WHERE type='admin' AND intitule='path_to_upload_folder'";
    $data = $db->queryFirst($sql);

    // Case ITEM ATTACHMENTS - Store to database
    if (isset($_POST['edit_item']) && $_POST['type_upload'] == "item_attachments") {
        $db->queryInsert(
            'files',
            array(
                'id_item' => $_POST['itemId'],
                'name' => $fileName,
                'size' => $_FILES['file']['size'],
                'extension' => getFileExtension($fileName),
                'type' => $_FILES['file']['type'],
                'file' => $fileRandomId
            )
        );
        // Log upload into databse only if "item edition"
        if (isset($_POST['edit_item']) && $_POST['edit_item'] == true) {
            $db->queryInsert(
                'log_items',
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
//}

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