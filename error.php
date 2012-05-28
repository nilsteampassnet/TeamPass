<?php
/**
 * @file 		error.php
 * @author		Nils Laumaillé
 * @version 	2.1
 * @copyright 	(c) 2009-2011 Nils Laumaillé
 * @licensing 	GNU AFFERO GPL 3.0
 * @link		http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

@session_start();
if (!isset($_SESSION['CPM'] ) || $_SESSION['CPM'] != 1)
	die('Hacking attempt...');

if (isset($_POST['session']) && $_POST['session'] == "expired"){
	//Include files
	require_once('includes/settings.php');
	require_once('includes/include.php');

	// connect to the server
	require_once("sources/class.database.php");
	$db = new Database($server, $user, $pass, $database, $pre);
	$db->connect();

	// Include main functions used by cpassman
	require_once('sources/main.functions.php');

	// Update table by deleting ID
	if ( isset($_SESSION['user_id']) )
		$db->query_update(
			"users",
			array(
			    'key_tempo' => ''
			),
			"id=".$_SESSION['user_id']
		);

	//Log into DB the user's disconnection
	if ( isset($_SESSION['settings']['log_connections']) && $_SESSION['settings']['log_connections'] == 1 )
		logEvents('user_connection','disconnection',$_SESSION['user_id']);

	// erase session table
	$_SESSION = array();

	// Kill session
	session_destroy();
}else{
	echo '
	<div style="width:800px;margin:auto;">';
	if ( @$_SESSION['error'] == 1000 ){
		echo '
		<div class="ui-state-error ui-corner-all error" >'.$txt['error_not_authorized'].'</div>';
	}else if ( @$_SESSION['error'] == 1001 ){
		echo '
		<div class="ui-state-error ui-corner-all error" >'.$txt['error_not_exists'].'</div>';
	}
}

echo '
</div>';
?>