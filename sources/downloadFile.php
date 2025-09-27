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
 * @file      downloadFile.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$antiXss = new AntiXSS();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

// Prepare GET variables
$getData = dataSanitizer(
    [
        'filename' => $request->query->get('name'),
        'fileid' => $request->query->get('fileid'),
        'action' => $request->query->get('action'),
        'file' => $request->query->get('file'),
        'key' => $request->query->get('key'),
        'key_tmp' => $request->query->get('key_tmp'),
        'pathIsFiles' => $request->query->get('pathIsFiles'),
    ],
    [
        'filename' => 'trim|escape',
        'fileid' => 'cast:integer',
        'action' => 'trim|escape',
        'file' => 'trim|escape',
        'key' => 'trim|escape',
        'key_tmp' => 'trim|escape',
        'pathIsFiles' => 'trim|escape',
    ]
);

/**
 * Send error response and exit
 */
function sendError($message) {
    // Clear any headers that might have been set
    if (!headers_sent()) {
        header_remove();
    }
    echo $message;
    exit;
}

/**
 * Set download headers safely
 */
function setDownloadHeaders($filename, $filesize = null) {
    // Clean filename for header - avoid rawurldecode
    $safeFilename = str_replace('"', '\\"', basename($filename));
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Cache-Control: must-revalidate, no-cache, no-store');
    header('Pragma: public');
    header('Expires: 0');
    
    if ($filesize !== null) {
        header('Content-Length: ' . $filesize);
    }
}

/**
 * Validate and secure file path
 */
function validateSecurePath($basePath, $filename) {
    if (empty($filename)) {
        return false;
    }
    
    $filepath = $basePath . '/' . basename($filename);
    
    // Security: Verify the resolved path is within the allowed directory
    $realBasePath = realpath($basePath);
    $realFilePath = realpath($filepath);
    
    if ($realFilePath === false || $realBasePath === false || 
        strpos($realFilePath, $realBasePath) !== 0) {
        return false;
    }
    
    return file_exists($filepath) && is_file($filepath) && is_readable($filepath) ? $filepath : false;
}

$get_filename = (string) $antiXss->xss_clean($getData['filename']);
$get_fileid = (int) $antiXss->xss_clean($getData['fileid']);
$get_pathIsFiles = (string) $antiXss->xss_clean($getData['pathIsFiles']);
$get_action = (string) $antiXss->xss_clean($getData['action']);
$get_file = (string) $antiXss->xss_clean($getData['file']);
$get_key = (string) $antiXss->xss_clean($getData['key']);
$get_key_tmp = (string) $antiXss->xss_clean($getData['key_tmp']);

// Branch 1: Files from files folder (pathIsFiles = 1)
if (null !== $get_pathIsFiles && (int) $get_pathIsFiles === 1) {

    // Clean filename
    $get_filename = str_replace(array("\r", "\n"), '', $get_filename);
    $get_filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '', basename($get_filename));
    
    if (empty($get_filename)) {
        sendError('ERROR_Invalid_filename');
    }
    
    // Validate file path
    $filepath = validateSecurePath($SETTINGS['path_to_files_folder'], $get_filename);
    if (!$filepath) {
        sendError('ERROR_File_not_found');
    }
    
    // Check permissions based on action
    $hasAccess = false;
    if ($get_action === 'backup') {
        $hasAccess = userHasAccessToBackupFile(
            $session->get('user-id'), 
            $get_file, 
            $get_key, 
            $get_key_tmp
        );
    } else {
        $hasAccess = userHasAccessToFile($session->get('user-id'), $get_fileid);
    }
    
    if (!$hasAccess) {
        sendError('ERROR_Not_allowed');
    }
    
    // All checks passed - serve file
    setDownloadHeaders($get_filename, filesize($filepath));
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    readfile($filepath);
    exit;
}

// Branch 2: Files from upload folder (encrypted/standard)
else {
    
    if (empty($get_fileid)) {
        sendError('ERROR_Invalid_fileid');
    }
    
    // Try to get encrypted file info first
    $file_info = DB::queryFirstRow(
        'SELECT f.id AS id, f.file AS file, f.name AS name, f.status AS status, f.extension AS extension,
        s.share_key AS share_key
        FROM ' . prefixTable('files') . ' AS f
        INNER JOIN ' . prefixTable('sharekeys_files') . ' AS s ON (f.id = s.object_id)
        WHERE s.user_id = %i AND s.object_id = %i',
        $session->get('user-id'),
        $get_fileid
    );
    
    $isEncrypted = (DB::count() > 0);
    $fileContent = '';
    
    if ($isEncrypted) {
        // Decrypt the file
        $fileContent = decryptFile(
            $file_info['file'],
            $SETTINGS['path_to_upload_folder'],
            decryptUserObjectKey($file_info['share_key'], $session->get('user-private_key'))
        );
    } else {
        // Get unencrypted file info
        $file_info = DB::queryFirstRow(
            'SELECT f.id AS id, f.file AS file, f.name AS name, f.status AS status, f.extension AS extension
            FROM ' . prefixTable('files') . ' AS f
            WHERE f.id = %i',
            $get_fileid
        );
        
        if (DB::count() === 0) {
            sendError('ERROR_No_file_found');
        }
    }
    
    // Prepare filename for download
    $filename = str_replace('b64:', '', $file_info['name']);
    $filename = basename($filename, '.' . $file_info['extension']);
    $filename = isBase64($filename) === true ? base64_decode($filename) : $filename;
    $filename = $filename . '.' . $file_info['extension'];
    
    // Determine file path
    $candidatePath1 = $SETTINGS['path_to_upload_folder'] . '/' . TP_FILE_PREFIX . $file_info['file'];
    $candidatePath2 = $SETTINGS['path_to_upload_folder'] . '/' . TP_FILE_PREFIX . base64_decode($file_info['file']);
    
    $filePath = false;
    if (file_exists($candidatePath1)) {
        $filePath = realpath($candidatePath1);
    } elseif (file_exists($candidatePath2)) {
        $filePath = realpath($candidatePath2);
    }
    
    if (WIP === true) {
        error_log('downloadFile.php: filePath: ' . $filePath . " - ");
    }
    
    // Validate file path and security
    $uploadFolderPath = realpath($SETTINGS['path_to_upload_folder']);
    if (!$filePath || !is_readable($filePath) || strpos($filePath, $uploadFolderPath) !== 0) {
        sendError('ERROR_No_file_found');
    }
    
    // Set headers and serve file
    setDownloadHeaders($filename, filesize($filePath));
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    if (empty($fileContent)) {
        // Serve file directly from disk
        readfile($filePath);
    } elseif (is_string($fileContent)) {
        // Serve decrypted content
        echo base64_decode($fileContent);
    } else {
        sendError('ERROR_No_file_found');
    }
    
    exit;
}