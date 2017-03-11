<?php
/**
 * @file          downloadFile.php
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

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || !isset($_SESSION['key_tmp']) || !isset($_SESSION['key']) || $_SESSION['CPM'] != 1 || $_GET['key'] != $_SESSION['key'] || $_GET['key_tmp'] != $_SESSION['key_tmp'] || empty($_SESSION['key']) || empty($_SESSION['key_tmp']) ) {
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
    require_once 'main.functions.php';
    // connect to DB
    include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = 'db_error_handler';
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // get file key
    $file_info = DB::queryfirstrow("SELECT file, status FROM ".prefix_table("files")." WHERE id=%i", $_GET['fileid']);

    // Open the file
    $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$file_info['file'], 'rb');

    // Prepare encryption options
    $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");
    $iv = substr(hash("md5", "iv".$ascii_key), 0, 8);
    $key = substr(hash("md5", "ssapmeat1".$ascii_key, true), 0, 24);
    $opts = array('iv'=>$iv, 'key'=>$key);

    // should we encrypt/decrypt the file
    encrypt_or_decrypt_file($file_info['file'], $file_info['status'], $opts);

    // should we decrypt the attachment?
    if (isset($file_info['status']) && $file_info['status'] === "encrypted") {
        // Add the Mcrypt stream filter
        stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts);
    }

    // Read the file contents
    fpassthru($fp);
}