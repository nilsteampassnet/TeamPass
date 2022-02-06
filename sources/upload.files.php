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
 * @file      upload.files.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}

/*
//check for session
if (null !== filter_input(INPUT_POST, 'PHPSESSID', FILTER_SANITIZE_STRING)) {
    session_id(filter_input(INPUT_POST, 'PHPSESSID', FILTER_SANITIZE_STRING));
} elseif (isset($_GET['PHPSESSID'])) {
    session_id(filter_var($_GET['PHPSESSID'], FILTER_SANITIZE_STRING));
} else {
    handleUploadError('No Session was found.');
}
*/

// Load config if $SETTINGS not defined
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir']) === true) {
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

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS)) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

// load functions
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

// Prepare POST variables
$post_user_token = filter_input(INPUT_POST, 'user_token', FILTER_SANITIZE_STRING);
$post_type_upload = filter_input(INPUT_POST, 'type_upload', FILTER_SANITIZE_STRING);
$post_newFileName = filter_input(INPUT_POST, 'newFileName', FILTER_SANITIZE_STRING);
$post_timezone = filter_input(INPUT_POST, 'timezone', FILTER_SANITIZE_STRING);

// Get parameters
$chunk = isset($_REQUEST['chunk']) ? intval($_REQUEST['chunk']) : 0;
$chunks = isset($_REQUEST['chunks']) ? intval($_REQUEST['chunks']) : 0;
$fileName = isset($_REQUEST['name']) ? filter_var($_REQUEST['name'], FILTER_SANITIZE_STRING) : '';

// token check
if (null === $post_user_token) {
    handleUploadError('No user token found.');
    exit();
} else {
    // delete expired tokens
    DB::delete(prefixTable('tokens'), 'end_timestamp < %i', time());

    if ($chunk < ($chunks - 1)) {
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
        // check if token is expired
        $data = DB::queryFirstRow(
            'SELECT end_timestamp FROM ' . prefixTable('tokens') . ' WHERE user_id = %i AND token = %s',
            $_SESSION['user_id'],
            $post_user_token
        );
        // clear user token
        DB::delete(
            prefixTable('tokens'),
            'user_id = %i AND token = %s',
            $_SESSION['user_id'],
            $post_user_token
        );

        if ($data !== null) {
            if (time() > $data['end_timestamp']) {
                // too old
                handleUploadError('User token expired.');
                die();
            }
        }
    }
}

// HTTP headers for no cache etc
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);

if (null !== $post_type_upload && $post_type_upload === 'upload_profile_photo') {
    $targetDir = $SETTINGS['cpassman_dir'] . '/includes/avatars';
} else {
    $targetDir = $SETTINGS['path_to_files_folder'];
}

$cleanupTargetDir = true; // Remove old files
$maxFileAge = 5 * 3600; // Temp file age in seconds
$valid_chars_regex = 'A-Za-z0-9_.'; //accept only those characters
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
    handleUploadError('POST exceeded maximum allowed size.');
    return false;
}

// Validate the file size (Warning: the largest files supported by this code is 2GB)
$file_size = @filesize($_FILES['file']['tmp_name']);
if ($file_size === false || $file_size > $max_file_size_in_bytes) {
    handleUploadError('File exceeds the maximum allowed size');
    return false;
}
if ($file_size <= 0) {
    handleUploadError('File size outside allowed lower bound');
    return false;
}

// 5 minutes execution time
set_time_limit(5 * 60);

// Validate the upload
if (isset($_FILES['file']) === false) {
    handleUploadError('No upload found in $_FILES for Filedata');
    return false;
} elseif (
    isset($_FILES['file']['error']) === true
    && $_FILES['file']['error'] != 0
) {
    handleUploadError($uploadErrors[$_FILES['Filedata']['error']]);
    return false;
} elseif (
    isset($_FILES['file']['tmp_name']) === false
    || @is_uploaded_file($_FILES['file']['tmp_name']) === false
) {
    handleUploadError('Upload failed is_uploaded_file test.');
    return false;
} elseif (isset($_FILES['file']['name']) === false) {
    handleUploadError('File has no name.');
    return false;
}

// Validate file name (for our purposes we'll just remove invalid characters)
$file_name = preg_replace(
    '/[^' . $valid_chars_regex . '\.]/',
    '',
    filter_var(
        strtolower(basename($_FILES['file']['name'])),
        FILTER_SANITIZE_STRING
    )
);
if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH) {
    handleUploadError('Invalid file name: ' . $file_name . '.');
    return false;
}

// Validate file extension
$ext = strtolower(
    getFileExtension(
        filter_var($_FILES['file']['name'], FILTER_SANITIZE_STRING)
    )
);
if (
    in_array(
        $ext,
        explode(
            ',',
            $SETTINGS['upload_docext'] . ',' . $SETTINGS['upload_imagesext'] .
                ',' . $SETTINGS['upload_pkgext'] . ',' . $SETTINGS['upload_otherext']
        )
    ) === false
    && $post_type_upload !== 'import_items_from_keepass'
) {
    handleUploadError('Invalid file extension.');
    die();
}

// is destination folder writable
if (is_writable($SETTINGS['path_to_files_folder']) === false) {
    handleUploadError('Not enough permissions on folder ' . $SETTINGS['path_to_files_folder'] . '.');
    return false;
}

// Clean the fileName for security reasons
$fileName = preg_replace('/[^\w\.]+/', '_', $fileName);
$fileName = preg_replace('/[^' . $valid_chars_regex . '\.]/', '', strtolower(basename($fileName)));

// Make sure the fileName is unique but only if chunking is disabled
if ($chunks < 2 && file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
    $fileNameA = substr($fileName, 0, strlen($ext));
    $fileNameB = substr($fileName, strlen($ext));

    $count = 1;
    while (file_exists($targetDir . DIRECTORY_SEPARATOR . $fileNameA . '_' . $count . $fileNameB)) {
        ++$count;
    }

    $fileName = $fileNameA . '_' . $count . $fileNameB;
}

$filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

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
    if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        // Open temp file
        $out = fopen("{$filePath}.part", $chunk == 0 ? 'wb' : 'ab');
        if ($out !== false) {
            // Read binary input stream and append it to temp file
            $in = fopen($_FILES['file']['tmp_name'], 'rb');

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
            die('{"jsonrpc" : "2.0",
                "error" : {"code": 102, "message": "Failed to open output stream."},
                "id" : "id"}');
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

// generate file name
$newFileName = bin2hex(GenerateCryptKey(16, false, true, true, false, true, $SETTINGS));

//Connect to mysql server
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;
$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, (int) DB_PORT, null);

if (
    null !== ($post_type_upload)
    && empty($post_type_upload) === false
    && $post_type_upload === 'import_items_from_csv'
) {
    rename(
        $filePath,
        $targetDir . DIRECTORY_SEPARATOR . $newFileName
    );

    // Add in DB
    DB::insert(
        prefixTable('misc'),
        array(
            'type' => 'temp_file',
            'intitule' => time(),
            'valeur' => $newFileName,
        )
    );

    // return info
    echo prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array(
            'operation_id' => DB::insertId(),
        ),
        'encode'
    );

    exit();
} elseif (
    null !== ($post_type_upload)
    && $post_type_upload === 'import_items_from_keepass'
) {
    rename(
        $filePath,
        $targetDir . DIRECTORY_SEPARATOR . $newFileName
    );

    // Add in DB
    DB::insert(
        prefixTable('misc'),
        array(
            'type' => 'temp_file',
            'intitule' => time(),
            'valeur' => $newFileName,
        )
    );

    // return info
    echo prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array(
            'operation_id' => DB::insertId(),
        ),
        'encode'
    );

    exit();
} elseif (
    null !== ($post_type_upload)
    && $post_type_upload === 'upload_profile_photo'
) {
    // get file extension
    $ext = (string) pathinfo($filePath, PATHINFO_EXTENSION);

    // rename the file
    rename(
        $filePath,
        $targetDir . DIRECTORY_SEPARATOR . $newFileName . '.' . $ext
    );

    // make thumbnail
    $ret = makeThumbnail(
        $targetDir . DIRECTORY_SEPARATOR . $newFileName . '.' . $ext,
        $targetDir . DIRECTORY_SEPARATOR . $newFileName . '_thumb' . '.' . $ext,
        40
    );

    if (empty($ret) === false) {
        echo $ret;
    
        exit();
    }

    // get current avatar and delete it
    $data = DB::queryFirstRow('SELECT avatar, avatar_thumb FROM ' . prefixTable('users') . ' WHERE id=%i', $_SESSION['user_id']);
    fileDelete($targetDir . DIRECTORY_SEPARATOR . $data['avatar'], $SETTINGS);
    fileDelete($targetDir . DIRECTORY_SEPARATOR . $data['avatar_thumb'], $SETTINGS);

    // store in DB the new avatar
    DB::query(
        'UPDATE ' . prefixTable('users') . "
        SET avatar='" . $newFileName . '.' . $ext . "', avatar_thumb='" . $newFileName . '_thumb' . '.' . $ext . "'
        WHERE id=%i",
        $_SESSION['user_id']
    );

    // store in session
    $_SESSION['user_avatar'] = $newFileName . '.' . $ext;
    $_SESSION['user_avatar_thumb'] = $newFileName . '_thumb' . '.' . $ext;

    // return info
    echo prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array(
            'filename' => htmlentities($_SESSION['user_avatar'], ENT_QUOTES),
            'filename_thumb' => htmlentities($_SESSION['user_avatar_thumb'], ENT_QUOTES),
        ),
        'encode'
    );

    exit();
} elseif (
    null !== ($post_type_upload)
    && $post_type_upload === 'restore_db'
) {
    rename(
        $filePath,
        $targetDir . DIRECTORY_SEPARATOR . $newFileName
    );

    // Add in DB
    DB::insert(
        prefixTable('misc'),
        array(
            'type' => 'temp_file',
            'intitule' => time(),
            'valeur' => $newFileName,
        )
    );

    // return info
    echo prepareExchangedData(
        $SETTINGS['cpassman_dir'],
        array(
            'operation_id' => DB::insertId(),
        ),
        'encode'
    );

    exit();
}

/**
 * Handles the error output.
 *
 * @param string $message Message to protect
 *
 * @return string
 */
function handleUploadError($message)
{
    echo htmlentities($message, ENT_QUOTES);

    return;
}
