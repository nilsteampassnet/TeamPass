<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @file      downloadFile.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$session = SessionManager::getSession();
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
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if (
    $checkUserAccess->userAccessPage('items') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
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
$get_filename = $superGlobal->get('name', 'GET');
$get_fileid = $superGlobal->get('fileid', 'GET');
$get_pathIsFiles = $superGlobal->get('pathIsFiles', 'GET');

// prepare Encryption class calls
header('Content-disposition: attachment; filename=' . rawurldecode(basename($get_filename)));
header('Content-Type: application/octet-stream');
header('Cache-Control: must-revalidate, no-cache, no-store');
header('Expires: 0');
if (isset($_GET['pathIsFiles']) && (int) $get_pathIsFiles === 1) {
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
    // Decrypt the file
    $fileContent = decryptFile(
        $file_info['file'],
        $SETTINGS['path_to_upload_folder'],
        decryptUserObjectKey($file_info['share_key'], $session->get('user-private_key'))
    );
    // Set the filename of the download
    $filename = base64_decode(basename($file_info['name'], '.'.$file_info['extension']));
    // Output CSV-specific headers
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '.' . $file_info['extension'] . '";');
    header('Content-Transfer-Encoding: binary');
    // Stream the CSV data
    exit(base64_decode($fileContent));
}
