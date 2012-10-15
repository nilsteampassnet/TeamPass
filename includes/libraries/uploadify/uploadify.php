<?php
/*
   Uploadify v2.1.4
   Release Date: November 8, 2010

   Copyright (c) 2010 Ronnie Garcia, Travis Nickels

   Permission is hereby granted, free of charge, to any person obtaining a copy
   of this software and associated documentation files (the "Software"), to deal
   in the Software without restriction, including without limitation the rights
   to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
   copies of the Software, and to permit persons to whom the Software is
   furnished to do so, subject to the following conditions:

   The above copyright notice and this permission notice shall be included in
   all copies or substantial portions of the Software.

   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
   IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
   FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
   AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
   LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
   OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
   THE SOFTWARE.
*/
/**
 * @file 		uploadify.php adapted for TeamPass
 * @author		Nils Laumaill�
 * @version 	2.1.8
 * @copyright 	(c) 2009-2011 Nils Laumaill�
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
//Variables
$MAX_FILENAME_LENGTH = 260;
$max_file_size_in_bytes = 2147483647;	//2Go
$valid_chars_regex = 'A-Za-z0-9';	//accept only those characters
$valid_exts = array('docx', 'xlsx', 'doc', 'xls', 'rtf', 'csv', 'jpg', 'jpeg', 'pdf', 'png', 'pot', 'ppt', 'shw', 'txt', 'tif', 'tiff', 'wpd', 'sql', 'xml');

//check for session
if (isset($_POST['PHPSESSID']))
	session_id($_POST['PHPSESSID']);
else if (isset($_GET['PHPSESSID']))
	session_id($_GET['PHPSESSID']);
else
{
	HandleError('No Session was found.');
}

session_start();
@date_default_timezone_set($_POST['timezone']);

// Check post_max_size
$POST_MAX_SIZE = ini_get('post_max_size');
$unit = strtoupper(substr($POST_MAX_SIZE, -1));
$multiplier = ($unit == 'M' ? 1048576 : ($unit == 'K' ? 1024 : ($unit == 'G' ? 1073741824 : 1)));
if ((int)$_SERVER['CONTENT_LENGTH'] > $multiplier*(int)$POST_MAX_SIZE && $POST_MAX_SIZE)
	HandleError('POST exceeded maximum allowed size.');

// Validate the file size (Warning: the largest files supported by this code is 2GB)
$file_size = @filesize($_FILES['Filedata']['tmp_name']);
if (!$file_size || $file_size > $max_file_size_in_bytes)
	HandleError('File exceeds the maximum allowed size');
if ($file_size <= 0)
	HandleError('File size outside allowed lower bound');

// Validate the upload
if (!isset($_FILES['Filedata']))
	HandleError('No upload found in $_FILES for Filedata');
else if (isset($_FILES['Filedata']['error']) && $_FILES['Filedata']['error'] != 0)
	HandleError($uploadErrors[$_FILES['Filedata']['error']]);
else if (!isset($_FILES['Filedata']['tmp_name']) || !@is_uploaded_file($_FILES['Filedata']['tmp_name']))
	HandleError('Upload failed is_uploaded_file test.');
else if (!isset($_FILES['Filedata']['name']))
	HandleError('File has no name.');

// Validate file name (for our purposes we'll just remove invalid characters)
$file_name = preg_replace('[^'.$valid_chars_regex.']', '', strtolower(basename($_FILES['Filedata']['name'])));
if (strlen($file_name) == 0 || strlen($file_name) > $MAX_FILENAME_LENGTH)
	HandleError('Invalid file name: '.$file_name);

// Validate file extension
$ext = get_file_extension($_FILES['Filedata']['name']);
if (!in_array($ext, $valid_exts))
	HandleError('Invalid file extension');

//Connect to mysql server
include('../../settings.php');
include('../../../sources/class.database.php');
$db = new Database($server, $user, $pass, $database, $pre);
$db->connect();

//Get data from DB
$sql = "SELECT valeur FROM ".$pre."misc WHERE type='admin' AND intitule='cpassman_dir'";
$data = $db->query_first($sql);
$targetPath = $data['valeur'];

//Treat the uploaded file
if ( isset($_POST['type_upload']) && ($_POST['type_upload'] == "import_items_from_csv" || $_POST['type_upload']== "import_items_from_file") ){
	$targetPath .= '/files/';
	$targetFile =  str_replace('//','/',$targetPath) . $_FILES['Filedata']['name'];

	// Log upload into databse - only log for a modification
	if ( isset($_POST['type']) && $_POST['type'] == "modification" ){
		$db->query_insert(
		'log_items',
		array(
		    'id_item' => $_POST['post_id'],
		    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
		    'id_user' => $_POST['user_id'],
		    'action' => 'at_modification',
		    'raison' => 'at_add_file : '.addslashes($_FILES['Filedata']['name'])
		)
		);
	}
}
//Case where upload is an attached file for one item
else if ( !isset($_POST['type_upload']) || ($_POST['type_upload'] != "import_items_from_file" && $_POST['type_upload'] != "restore_db") ){
	// Get some variables
	$file_random_id = md5($_FILES['Filedata']['name'].mktime(date('h'), date('i'), date('s'), date('m'), date('d'), date('Y')));

	//Get data from DB
	$sql = "SELECT valeur FROM ".$pre."misc WHERE type='admin' AND intitule='path_to_upload_folder'";
	$data = $db->query_first($sql);
	$targetPath = $data['valeur'];
	$targetFile =  str_replace('//','/',$targetPath)."/" . $file_random_id;

	// Store to database
	$db->query_insert(
	'files',
	array(
	    'id_item' => $_POST['post_id'],
	    'name' => str_replace(' ','_',$_FILES['Filedata']['name']),
	    'size' => $_FILES['Filedata']['size'],
	    'extension' => get_file_extension($_FILES['Filedata']['name']),
	    'type' => $_FILES['Filedata']['type'],
	    'file' => $file_random_id
	)
	);

	// Log upload into databse - only log for a modification
	if ( $_POST['type'] == "modification" ){
		$db->query_insert(
		'log_items',
		array(
		    'id_item' => $_POST['post_id'],
		    'date' => mktime(date('H'),date('i'),date('s'),date('m'),date('d'),date('y')),
		    'id_user' => $_POST['user_id'],
		    'action' => 'at_modification',
		    'raison' => 'at_add_file : '.addslashes($_FILES['Filedata']['name'])
		)
		);
	}
}else{
	// Get some variables
	$targetPath .= '/files/';
	$targetFile =  str_replace('//','/',$targetPath) . $_FILES['Filedata']['name'];
}

//move
if(move_uploaded_file($_FILES['Filedata']['tmp_name'], $targetFile))
	echo "ok";
else
	echo "nok";

// Permits to extract the file extension
function get_file_extension($f)
{
	if(strpos($f, '.') === false) return $f;
	return substr($f, strrpos($f, '.')+1);
}

/* Handles the error output. */
function HandleError($message) {
	echo $message;
	exit(0);
}
?>