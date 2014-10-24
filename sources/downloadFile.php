<?php
/**
 * @file          downloadFile.php
 * @author        Nils Laumaillé
 * @version       2.1.22
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || $_GET['key'] != $_SESSION['key'] || $_GET['key_tmp'] != $_SESSION['key_tmp']) {
    die('Hacking attempt...');
}

header("Content-disposition: attachment; filename=".rawurldecode($_GET['name']));
header("Content-Type: application/octet-stream");
header("Pragma: no-cache");
header("Cache-Control: must-revalidate, no-cache, no-store");
header("Expires: 0");
if (isset($_GET['pathIsFiles']) && $_GET['pathIsFiles'] == 1) {
	readfile($_SESSION['settings']['path_to_files_folder'].'/'.basename($_GET['file']));
} else {
    // Open the file
    $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.basename($_GET['file']), 'rb');

    // should we decrypt the attachment?
    if (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] == 1) {
        include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';

        // Prepare encryption options
        $iv = substr(md5("\x1B\x3C\x58".SALT, true), 0, 8);
        $key = substr(
            md5("\x2D\xFC\xD8".SALT, true) .
            md5("\x2D\xFC\xD9".SALT, true),
            0,
            24
        );
        $opts = array('iv'=>$iv, 'key'=>$key);

        // Add the Mcrypt stream filter
        stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts);
    }

    // Read the file contents
    fpassthru($fp);
}