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
 * @copyright 2009-2024 Teampass.net
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

// Prepare GET variables
$get_filename = (string) $antiXss->xss_clean($request->query->get('name'));
$get_fileid = $antiXss->xss_clean($request->query->get('fileid'));
$get_pathIsFiles = (string) $antiXss->xss_clean($request->query->get('pathIsFiles'));

// Remove newline characters from the filename
$get_filename = str_replace(array("\r", "\n"), '', $get_filename);

// prepare Encryption class calls
header('Content-disposition: attachment; filename=' . rawurldecode(basename($get_filename)));
header('Content-Type: application/octet-stream');
header('Cache-Control: must-revalidate, no-cache, no-store');
header('Expires: 0');
if (null !== $request->query->get('pathIsFiles') && (int) $get_pathIsFiles === 1) {
    readfile($SETTINGS['path_to_files_folder'] . '/' . basename($get_filename));
} else {
    // get file key
    $file_info = DB::queryfirstrow(
        'SELECT f.id AS id, f.file AS file, f.name AS name, f.status AS status, f.extension AS extension,
        s.share_key AS share_key
        FROM ' . prefixTable('files') . ' AS f
        INNER JOIN ' . prefixTable('sharekeys_files') . ' AS s ON (f.id = s.object_id)
        WHERE s.user_id = %i AND s.object_id = %i',
        $session->get('user-id'),
        $get_fileid
    );
    
    // if encrypted
    if (DB::count() > 0) {
        // Decrypt the file
        // deepcode ignore PT: File and path are secured directly inside the function decryptFile()
        $fileContent = decryptFile(
            $file_info['file'],
            $SETTINGS['path_to_upload_folder'],
            decryptUserObjectKey($file_info['share_key'], $session->get('user-private_key'))
        );
    } else {
        // if not encrypted
        $file_info = DB::queryfirstrow(
            'SELECT f.id AS id, f.file AS file, f.name AS name, f.status AS status, f.extension AS extension
            FROM ' . prefixTable('files') . ' AS f
            WHERE f.id = %i',
            $get_fileid
        );
        $fileContent = '';
    }

    // Set the filename of the download
    $filename = basename($file_info['name'], '.'.$file_info['extension']);
    $filename = isBase64($filename) === true ? base64_decode($filename) : $filename;
    $filename = $filename . '.' . $file_info['extension'];
    // Get the full path to the file to be downloaded
    if (file_exists($SETTINGS['path_to_upload_folder'] . '/' .TP_FILE_PREFIX . $file_info['file'])) {
        $filePath = $SETTINGS['path_to_upload_folder'] . '/' . TP_FILE_PREFIX . $file_info['file'];
    } else {
        $filePath = $SETTINGS['path_to_upload_folder'] . '/' . TP_FILE_PREFIX . base64_decode($file_info['file']);
    }
    $filePath = realpath($filePath);

    if (WIP === true) error_log('downloadFile.php: filePath: ' . $filePath." - ");

    if ($filePath && is_readable($filePath) && strpos($filePath, realpath($SETTINGS['path_to_upload_folder'])) === 0) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        flush(); // Clear system output buffer
        if (empty($fileContent) === true) {
            // deepcode ignore PT: File and path are secured directly inside the function decryptFile()
            readfile($filePath); // Read the file from disk
        } else {
            exit(base64_decode($fileContent));
        }
        exit;
    } else {
        echo 'ERROR_No_file_found';
        exit;
    }
}
