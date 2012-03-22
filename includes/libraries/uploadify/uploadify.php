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
 * @version 	2.1
 * @copyright 	(c) 2009-2011 Nils Laumaill�
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

@date_default_timezone_set($_POST['timezone']);

//Connect to mysql server
include('../../settings.php');
include('../../../sources/class.database.php');
$db = new Database($server, $user, $pass, $database, $pre);
$db->connect();

//Check if true user
$data = $db->fetch_row("SELECT key_tempo FROM ".$pre."users WHERE id = ".$_GET['user_id']);
if($data[0] != $_GET['key_tempo']) die('Hacking attempt...');

// Permits to extract the file extension
function findexts ($filename)
{
	$filename = strtolower($filename) ;
	$exts = preg_split("/\./", $filename) ;
	$n = count($exts)-1;
	$exts = $exts[$n];
	return $exts;
}

if (!empty($_FILES)) {
	if ( isset($_POST['type_upload']) && ($_POST['type_upload'] == "import_items_from_csv" || $_POST['type_upload']== "import_items_from_file") ){
		$targetPath = $_SERVER['DOCUMENT_ROOT'].$_REQUEST['folder'] . '/';
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
		$tempFile = $_FILES['Filedata']['tmp_name'];
		$targetPath = $_SERVER['DOCUMENT_ROOT'].$_REQUEST['folder'] . '/';
		$targetFile =  str_replace('//','/',$targetPath) . $file_random_id;

		// Store to database
		$db->query_insert(
		'files',
		array(
		    'id_item' => $_POST['post_id'],
		    'name' => str_replace(' ','_',$_FILES['Filedata']['name']),
		    'size' => $_FILES['Filedata']['size'],
		    'extension' => findexts($_FILES['Filedata']['name']),
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
		//$tempFile = $_FILES['Filedata']['tmp_name'];
		$targetPath = $_SERVER['DOCUMENT_ROOT'].$_REQUEST['folder'] . '/';
		$targetFile =  str_replace('//','/',$targetPath) . $_FILES['Filedata']['name'];
	}

	//move
	move_uploaded_file($_FILES['Filedata']['tmp_name'], $targetFile);
	echo $targetFile;

}
?>