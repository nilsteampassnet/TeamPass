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
 * @file      upload.files.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as RequestLocal;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';
$session = SessionManager::getSession();
// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = RequestLocal::createFromGlobals();
$lang = new Language(); 


// Load config if $SETTINGS not defined
if (empty($SETTINGS)) {
    $configManager = new ConfigManager();
    $SETTINGS = $configManager->getAllSettings();
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

// --------------------------------- //

//check for session
if (null !== $request->request->filter('PHPSESSID', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS)) {
    session_id($request->request->filter('PHPSESSID', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
} elseif (null !== $request->query->get('PHPSESSID')) {
    session_id(filter_var($request->query->get('PHPSESSID'), FILTER_SANITIZE_FULL_SPECIAL_CHARS));
} else {
    echo handleUploadError('No Session was found.');
    return false;
}

// Prepare POST variables
$post_user_token = $request->request->filter('user_upload_token', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_type_upload = $request->request->filter('type_upload', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_timezone = $request->request->filter('timezone', null, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$chunk = $request->request->filter('chunk', 0, FILTER_SANITIZE_NUMBER_INT);
$chunks = $request->request->filter('chunks', 0, FILTER_SANITIZE_NUMBER_INT);
$fileName = $request->request->filter('name', '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// token check
if (null === $post_user_token) {
    echo handleUploadError('No user token found.');
    return false;
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
            $session->get('user-id'),
            $post_user_token
        );
    } else {
        // check if token is expired
        $data = DB::queryFirstRow(
            'SELECT end_timestamp FROM ' . prefixTable('tokens') . ' WHERE user_id = %i AND token = %s',
            $session->get('user-id'),
            $post_user_token
        );
        // clear user token
        DB::delete(
            prefixTable('tokens'),
            'user_id = %i AND token = %s',
            $session->get('user-id'),
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
    // User profile edit disabled
    if (($SETTINGS['disable_user_edit_profile'] ?? '0') === '1') {
        echo handleUploadError('Avatar upload not allowed.');
        return false;
    }

    // Set directory used to store file
    $targetDir = realpath($SETTINGS['cpassman_dir'] . '/includes/avatars');
} else {
    $targetDir = realpath($SETTINGS['path_to_files_folder']);
}

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
    echo handleUploadError('POST exceeded maximum allowed size.');
    return false;
}

// Validate file size (Warning: the largest files supported by this code is 2GB)
$file = $request->files->get('file');

if ($file) {
    // Get the size of the uploaded file
    $file_size = $file->getSize();

    if ($file_size === false || $file_size > $max_file_size_in_bytes) {
        echo handleUploadError('File exceeds the maximum allowed size');
        return false;
    }
    
    if ($file_size <= 0) {
        echo handleUploadError('File size outside allowed lower bound');
        return false;
    }

    // 5 minutes execution time
    set_time_limit(5 * 60);

    // Validate upload
    if ($file->getError() !== UPLOAD_ERR_OK) {
        echo handleUploadError($uploadErrors[$file->getError()]);
        return false;
    }

    // Validate file name (for our purposes we'll just remove invalid characters)
    $file_name = preg_replace('/[^a-zA-Z0-9-_\.]/', '', strtolower(basename($file->getClientOriginalName())));
    
    if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH) {
        error_log('Invalid file name: ' . $file_name . '.');
        echo handleUploadError('Invalid file name provided.');
        return false;
    }
    
    // Check that file is a valid string
    $originalName = $file->getClientOriginalName();
    if (is_string($originalName)) {
        // Get file extension
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        if (is_string($ext)) {
            $ext = strtolower($ext);
        } else {
            // Case where the file extension is not a string
            error_log('Invalid file name: ' . $file_name . '.');
            echo handleUploadError('Invalid file extension.');
            return false;
        }
    } else {
        // Case where the file name is not a string
        error_log('Invalid file name: ' . $file_name . '.');
        echo handleUploadError('Invalid file.');
        return false;
    }

    // Validate against a list of allowed extensions
    $allowed_extensions = explode(
        ',',
        $SETTINGS['upload_docext'] . ',' . $SETTINGS['upload_imagesext'] .
            ',' . $SETTINGS['upload_pkgext'] . ',' . $SETTINGS['upload_otherext']
    );
    if (
        !in_array($ext, $allowed_extensions) 
        && $post_type_upload !== 'import_items_from_keepass'
        && $post_type_upload !== 'import_items_from_csv'
        && $post_type_upload !== 'restore_db'
        && $post_type_upload !== 'upload_profile_photo'
    ) {
        echo handleUploadError('Invalid file extension.');
        return false;
    }
} else {
    echo handleUploadError('No upload found for Filedata');
    return false;
}

// is destination folder writable
if (is_writable($SETTINGS['path_to_files_folder']) === false) {
    echo handleUploadError('Not enough permissions on folder ' . $SETTINGS['path_to_files_folder'] . '.');
    return false;
}

// Make sure the fileName is unique but only if chunking is disabled
if ($chunks < 2 && file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
    // $ext is guaranteed to be a string due to prior checks
    $fileNameA = substr($fileName, 0, strlen(/** @scrutinizer ignore-type */$ext));
    $fileNameB = substr($fileName, strlen(/** @scrutinizer ignore-type */$ext));

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
    while (($fileClean = readdir($dir)) !== false) {
        $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $fileClean;

        // Remove temp file if it is older than the max age and is not the current file
        if (
            preg_match('/\.part$/', $fileClean)
            && (filemtime($tmpfilePath) < time() - $maxFileAge)
            && ($tmpfilePath != "{$filePath}.part")
        ) {
            fileDelete($tmpfilePath, $SETTINGS);
        }
    }

    closedir($dir);
} else {
    echo handleUploadError('Not enough permissions on folder ' . $SETTINGS['path_to_files_folder'] . '.');
    return false;
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
    if ($file && $file->isValid()) {
        // Path for the temporary file
        $tempFilePath = "{$filePath}.part";
        
        // Open the output file
        try {
            $out = fopen($tempFilePath, $chunk == 0 ? 'wb' : 'ab');
            
            if ($out === false) {
                throw new FileException('Failed to open output stream.');
            }
    
            // Open the uploaded temporary file
            // But before we will move the file to a temporary location
            // Temporary path of the uploaded file
            $tmpFilePath = $file->getPathname();

            // Has the file being uploaded via HPPT POST
            if (is_uploaded_file($tmpFilePath)) {
                // Clear file name
                $fileName = basename($file->getClientOriginalName());
                $fileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName);

                // Safe destination folder
                $uploadDir = realpath($SETTINGS['path_to_upload_folder']);
                $destinationPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                
                if (move_uploaded_file($tmpFilePath, $destinationPath)) {
                    // Open the moved file in read mode
                    $in = fopen($destinationPath, 'rb');
                } else {
                    // Do we have errors
                    echo handleUploadError('Error while moving the uploaded file.');
                    exit;
                }
            } else {
                // file has not being uploaded via HTTP POST
                echo handleUploadError('FErreur : fichier non valide.');
                exit;
            }
    
            if ($in === false) {
                throw new FileException('Failed to open input stream.');
            }
    
            // Read and write to the output file
            while ($buff = fread($in, 4096)) {
                fwrite($out, $buff);
            }
    
            // Close the files
            fclose($in);
            fclose($out);
    
            // Delete temporary file if needed
            fileDelete($destinationPath, $SETTINGS);
            
        } catch (FileException $e) {
            // On error log
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log($e->getMessage());
            }
            echo handleUploadError('File processing failed.');
            return false;
        }
    } else {
        echo handleUploadError('Failed to move uploaded file to ' . $SETTINGS['path_to_files_folder'] . '.');
        return false;
    }
} else {
    // Open temp file
    // deepcode ignore PT: $filePath is escaped and secured previously
    $out = fopen("{$filePath}.part", $chunk == 0 ? 'wb' : 'ab');
    if ($out !== false) {
        // Read binary input stream and append it to temp file
        $in = fopen('php://input', 'rb');

        if ($in !== false) {
            while ($buff = fread($in, 4096)) {
                fwrite($out, $buff);
            }
        } else {
            echo handleUploadError('Failed to open input stream ' . $SETTINGS['path_to_files_folder'] . '.');
            return false;
        }

        fclose($in);
        fclose($out);
    } else {
        echo handleUploadError('Failed to open output stream ' . $SETTINGS['path_to_files_folder'] . '.');
        return false;
    }
}

// Check if file has been uploaded
if (!$chunks || $chunk == $chunks - 1) {
    // Strip the temp .part suffix off
    // deepcode ignore PT: $filePath is escaped and secured previously
    rename("{$filePath}.part", $filePath);
} else {
    // continue uploading other chunks
    echo prepareExchangedData(
        array(
            'error' => false,
            'chunk' => (int) $chunk + 1,
            'chunks' => $chunks,
        ),
        'encode'
    );
    die();
}

// generate file name
$newFileName = bin2hex(GenerateCryptKey(16, false, true, true, false, true));

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
        array(
            'error' => false,
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
        array(
            'error' => false,
            'operation_id' => DB::insertId(),
        ),
        'encode'
    );

    exit();
} elseif (
    null !== ($post_type_upload)
    && $post_type_upload === 'upload_profile_photo'
) {
    // PNG is the only supported extension
    $ext = "png";

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
        // deepcode ignore XSS: $ret contains only an error message without any information, or false if error during thumbnail creation
        echo $ret;
    
        exit();
    }

    // get current avatar and delete it
    $data = DB::queryFirstRow('SELECT avatar, avatar_thumb FROM ' . prefixTable('users') . ' WHERE id=%i', $session->get('user-id'));
    fileDelete($targetDir . DIRECTORY_SEPARATOR . $data['avatar'], $SETTINGS);
    fileDelete($targetDir . DIRECTORY_SEPARATOR . $data['avatar_thumb'], $SETTINGS);

    // store in DB the new avatar
    DB::query(
        'UPDATE ' . prefixTable('users') . "
        SET avatar='" . $newFileName . '.' . $ext . "', avatar_thumb='" . $newFileName . '_thumb' . '.' . $ext . "'
        WHERE id=%i",
        $session->get('user-id')
    );

    // store in session
    $session->set('user-avatar', $newFileName . '.' . $ext);
    $session->set('user-avatar_thumb', $newFileName . '_thumb' . '.' . $ext);

    // return info
    echo prepareExchangedData(
        array(
            'error' => false,
            'filename' => htmlentities($session->get('user-avatar'), ENT_QUOTES),
            'filename_thumb' => htmlentities($session->get('user-avatar_thumb'), ENT_QUOTES),
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
        array(
            'error' => false,
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
function handleUploadError($message): string
{
    return prepareExchangedData(
        array(
            'error' => true,
            'message' => $message,
        ),
        'encode'
    );
}
